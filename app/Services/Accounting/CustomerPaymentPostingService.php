<?php

namespace App\Services\Accounting;

use App\Enums\AuditAction;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerPaymentPostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    public function post(CustomerPayment $payment, int $userId): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $userId): CustomerPayment {
            // Row-level lock serializes concurrent postings of the same payment.
            /** @var CustomerPayment $locked */
            $locked = CustomerPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CustomerPaymentStatus::DRAFT) {
                throw new \InvalidArgumentException('Seul un règlement en brouillon peut être comptabilisé.');
            }

            $locked->load([
                'customer', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod', 'allocations',
            ]);

            $this->validatePreconditions($locked);
            $this->validateContextData($locked);

            // Concurrency gate: invoice rows are locked in a deterministic order
            // so two payments racing on the same invoice are serialized. The
            // outstanding balances are then recalculated inside the transaction.
            $invoiceIds = $locked->allocations
                ->map(fn ($allocation) => (int) $allocation->invoice_id)
                ->unique()
                ->sort()
                ->values();

            /** @var Collection<int, Invoice> $invoices */
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $allocatedTotal = '0';

            foreach ($locked->allocations as $allocation) {
                $amount = $this->decimalFromDb($allocation->amount);

                if (bccomp($amount, '0', 3) <= 0) {
                    throw new \InvalidArgumentException('Le montant imputé doit être strictement positif.');
                }

                /** @var Invoice|null $invoice */
                $invoice = $invoices->get((int) $allocation->invoice_id);

                if ($invoice === null) {
                    throw new \InvalidArgumentException('La facture imputée est introuvable.');
                }

                if ($invoice->company_id !== $locked->company_id) {
                    throw new \InvalidArgumentException('La facture imputée n\'appartient pas à cette société.');
                }

                if ($invoice->status !== InvoiceStatus::POSTED) {
                    throw new \InvalidArgumentException(
                        'La facture '.$invoice->invoice_number.' doit être comptabilisée pour être imputée.'
                    );
                }

                if ($invoice->customer_id !== $locked->customer_id) {
                    throw new \InvalidArgumentException('La facture imputée n\'appartient pas au client du règlement.');
                }

                // Recalculated inside the locked transaction: posted credit notes
                // and allocations from other POSTED payments only.
                $remaining = bcsub(
                    bcsub(
                        $this->decimalFromDb($invoice->total),
                        $this->postedCreditNotesTotal((int) $invoice->id),
                        3
                    ),
                    $this->postedAllocationsTotal((int) $invoice->id),
                    3
                );

                if (bccomp($remaining, '0', 3) < 0) {
                    $remaining = '0';
                }

                if (bccomp($amount, $remaining, 3) > 0) {
                    throw new \InvalidArgumentException(
                        'Imputation supérieure au reste à régler de la facture '.$invoice->invoice_number.' (reste : '.$remaining.'). Un autre encaissement ou avoir a peut-être été comptabilisé entre-temps.'
                    );
                }

                $allocatedTotal = bcadd($allocatedTotal, $amount, 3);
            }

            if (bccomp($allocatedTotal, $this->decimalFromDb($locked->amount), 3) > 0) {
                throw new \InvalidArgumentException('Le total des imputations dépasse le montant du règlement.');
            }

            $receivableAccountId = $this->resolveReceivableAccount($locked);
            $this->validateReceivableAccount($receivableAccountId, $locked);

            $journalEntry = $this->createJournalEntry($locked, $userId);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $locked->destination_account_id,
                'description' => "Encaissement {$locked->payment_number} — {$locked->paymentMethod->name}",
                'debit' => $this->decimalFromDb($locked->amount),
                'credit' => '0.000',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $receivableAccountId,
                'description' => "Encaissement {$locked->payment_number} — Client: {$locked->customer->name}",
                'debit' => '0.000',
                'credit' => $this->decimalFromDb($locked->amount),
            ]);

            if (! $journalEntry->fresh()->isBalanced()) {
                throw new \RuntimeException('L\'écriture comptable n\'est pas équilibrée.');
            }

            $journalEntry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);

            $locked->update([
                'status' => CustomerPaymentStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            $locked = $locked->fresh([
                'allocations.invoice', 'customer', 'paymentMethod', 'journal',
                'destinationAccount', 'fiscalYear', 'accountingPeriod',
                'journalEntry.lines.account', 'creator',
            ]);

            $this->audits()->logAction(
                AuditAction::CustomerPaymentPosted,
                "Règlement client comptabilisé : {$locked->payment_number}.",
                entity: $locked,
                metadata: ['payment_number' => $locked->payment_number, 'amount' => (string) $locked->amount, 'journal_entry_number' => $journalEntry->entry_number],
            );

            return $locked;
        });
    }

    private function validatePreconditions(CustomerPayment $payment): void
    {
        if (bccomp($this->decimalFromDb($payment->amount), '0', 3) <= 0) {
            throw new \InvalidArgumentException('Le montant du règlement doit être strictement positif.');
        }

        if ($payment->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé. Impossible de comptabiliser.');
        }

        if ($payment->accountingPeriod->is_closed || ! $payment->accountingPeriod->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée. Impossible de comptabiliser.');
        }

        $periodStart = Carbon::parse($payment->accountingPeriod->start_date);
        $periodEnd = Carbon::parse($payment->accountingPeriod->end_date);
        $paymentDate = $payment->payment_date;

        if ($paymentDate->lt($periodStart) || $paymentDate->gt($periodEnd)) {
            throw new \InvalidArgumentException('La date du règlement doit être comprise dans la période comptable.');
        }
    }

    /**
     * Master data is revalidated at posting time: the payment method, journal,
     * and destination account may have been deactivated since the draft was created.
     */
    private function validateContextData(CustomerPayment $payment): void
    {
        $method = $payment->paymentMethod;

        if (! $method || $method->company_id !== $payment->company_id) {
            throw new \InvalidArgumentException('Le moyen de paiement sélectionné n\'appartient pas à cette société.');
        }

        if (! $method->is_active) {
            throw new \InvalidArgumentException('Le moyen de paiement sélectionné est inactif. Impossible de comptabiliser.');
        }

        $journalExists = DB::table('journals')
            ->where('id', $payment->journal_id)
            ->where('company_id', $payment->company_id)
            ->where('fiscal_year_id', $payment->fiscal_year_id)
            ->where('is_active', true)
            ->exists();

        if (! $journalExists) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'existe pas dans cette société/exercice ou est inactif.');
        }

        $destinationExists = DB::table('accounts')
            ->where('id', $payment->destination_account_id)
            ->where('company_id', $payment->company_id)
            ->where('fiscal_year_id', $payment->fiscal_year_id)
            ->where('is_active', true)
            ->exists();

        if (! $destinationExists) {
            throw new \InvalidArgumentException('Le compte de destination n\'appartient pas à cette société/exercice ou est inactif.');
        }
    }

    private function resolveReceivableAccount(CustomerPayment $payment): int
    {
        if ($payment->customer->account_id) {
            return $payment->customer->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $payment->company_id)->first();
        if ($settings && $settings->default_customer_account_id) {
            return $settings->default_customer_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte client configuré pour ce client. Veuillez assigner un compte de tiers au client ou configurer un compte client par défaut dans les paramètres comptables.');
    }

    private function validateReceivableAccount(int $accountId, CustomerPayment $payment): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $payment->company_id)
            ->where('fiscal_year_id', $payment->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function createJournalEntry(CustomerPayment $payment, int $userId): JournalEntry
    {
        return JournalEntry::create([
            'company_id' => $payment->company_id,
            'fiscal_year_id' => $payment->fiscal_year_id,
            'accounting_period_id' => $payment->accounting_period_id,
            'journal_id' => $payment->journal_id,
            'entry_number' => $this->journalEntryService->generateEntryNumber(
                $payment->company_id,
                $payment->fiscal_year_id,
                $payment->journal_id
            ),
            'entry_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => "Encaissement {$payment->payment_number} — {$payment->customer->name}",
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    /**
     * Posted credit-note impact on an invoice (draft/cancelled excluded).
     *
     * @return numeric-string
     */
    private function postedCreditNotesTotal(int $invoiceId): string
    {
        $value = DB::table('credit_notes')
            ->where('invoice_id', $invoiceId)
            ->where('status', CreditNoteStatus::POSTED->value)
            ->sum('total');

        return $this->decimalFromDb($value);
    }

    /**
     * Allocated amounts from POSTED payments only (draft/cancelled excluded).
     *
     * @return numeric-string
     */
    private function postedAllocationsTotal(int $invoiceId): string
    {
        $value = DB::table('customer_payment_allocations')
            ->join('customer_payments', 'customer_payments.id', '=', 'customer_payment_allocations.customer_payment_id')
            ->where('customer_payment_allocations.invoice_id', $invoiceId)
            ->where('customer_payments.status', CustomerPaymentStatus::POSTED->value)
            ->sum('customer_payment_allocations.amount');

        return $this->decimalFromDb($value);
    }

    /**
     * Normalize a value into a fixed 3-decimal numeric string.
     *
     * @return numeric-string
     */
    private function decimalFromDb(mixed $value): string
    {
        return number_format((float) str_replace(',', '', (string) $value), 3, '.', '');
    }
}
