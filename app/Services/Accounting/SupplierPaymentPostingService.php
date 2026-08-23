<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseInvoice;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SupplierPaymentPostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
    ) {}

    public function post(SupplierPayment $payment, int $userId): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $userId): SupplierPayment {
            // Row-level lock serializes concurrent postings of the same payment.
            /** @var SupplierPayment $locked */
            $locked = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SupplierPaymentStatus::DRAFT) {
                throw new \InvalidArgumentException('Seul un règlement en brouillon peut être comptabilisé.');
            }

            $locked->load([
                'supplier', 'paymentMethod', 'journal', 'destinationAccount',
                'fiscalYear', 'accountingPeriod', 'allocations',
            ]);

            $this->validatePreconditions($locked);
            $this->validateContextData($locked);

            // Concurrency gate: purchase-invoice rows are locked in a deterministic
            // order so two payments racing on the same invoice are serialized. The
            // outstanding balances are then recalculated inside the transaction.
            $invoiceIds = $locked->allocations
                ->map(fn ($allocation) => (int) $allocation->purchase_invoice_id)
                ->unique()
                ->sort()
                ->values();

            /** @var Collection<int, PurchaseInvoice> $invoices */
            $invoices = PurchaseInvoice::whereIn('id', $invoiceIds)
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

                /** @var PurchaseInvoice|null $invoice */
                $invoice = $invoices->get((int) $allocation->purchase_invoice_id);

                if ($invoice === null) {
                    throw new \InvalidArgumentException('La facture imputée est introuvable.');
                }

                if ($invoice->company_id !== $locked->company_id) {
                    throw new \InvalidArgumentException('La facture imputée n\'appartient pas à cette société.');
                }

                if ($invoice->status !== PurchaseInvoiceStatus::POSTED) {
                    throw new \InvalidArgumentException(
                        'La facture '.$invoice->invoice_number.' doit être comptabilisée pour être imputée.'
                    );
                }

                if ($invoice->supplier_id !== $locked->supplier_id) {
                    throw new \InvalidArgumentException('La facture imputée n\'appartient pas au fournisseur du règlement.');
                }

                // Recalculated inside the locked transaction: allocations from
                // other POSTED payments only (no supplier credit notes exist).
                $remaining = bcsub(
                    $this->decimalFromDb($invoice->total),
                    $this->postedAllocationsTotal((int) $invoice->id),
                    3
                );

                if (bccomp($remaining, '0', 3) < 0) {
                    $remaining = '0';
                }

                if (bccomp($amount, $remaining, 3) > 0) {
                    throw new \InvalidArgumentException(
                        'Imputation supérieure au reste à régler de la facture '.$invoice->invoice_number.' (reste : '.$remaining.'). Un autre règlement a peut-être été comptabilisé entre-temps.'
                    );
                }

                $allocatedTotal = bcadd($allocatedTotal, $amount, 3);
            }

            if (bccomp($allocatedTotal, $this->decimalFromDb($locked->amount), 3) > 0) {
                throw new \InvalidArgumentException('Le total des imputations dépasse le montant du règlement.');
            }

            $payableAccountId = $this->resolvePayableAccount($locked);
            $this->validatePayableAccount($payableAccountId, $locked);

            $journalEntry = $this->createJournalEntry($locked, $userId);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $payableAccountId,
                'description' => "Règlement {$locked->payment_number} — Fournisseur: {$locked->supplier->name}",
                'debit' => $this->decimalFromDb($locked->amount),
                'credit' => '0.000',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $locked->destination_account_id,
                'description' => "Règlement {$locked->payment_number} — {$locked->paymentMethod->name}",
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
                'status' => SupplierPaymentStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $locked->fresh([
                'allocations.purchaseInvoice', 'supplier', 'paymentMethod', 'journal',
                'destinationAccount', 'fiscalYear', 'accountingPeriod',
                'journalEntry.lines.account', 'creator',
            ]);
        });
    }

    private function validatePreconditions(SupplierPayment $payment): void
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
    private function validateContextData(SupplierPayment $payment): void
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

    private function resolvePayableAccount(SupplierPayment $payment): int
    {
        if ($payment->supplier->account_id) {
            return $payment->supplier->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $payment->company_id)->first();
        if ($settings && $settings->default_supplier_account_id) {
            return $settings->default_supplier_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte fournisseur configuré pour ce fournisseur. Veuillez assigner un compte de tiers au fournisseur ou configurer un compte fournisseur par défaut dans les paramètres comptables.');
    }

    private function validatePayableAccount(int $accountId, SupplierPayment $payment): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $payment->company_id)
            ->where('fiscal_year_id', $payment->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function createJournalEntry(SupplierPayment $payment, int $userId): JournalEntry
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
            'description' => "Règlement fournisseur {$payment->payment_number} — {$payment->supplier->name}",
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    /**
     * Allocated amounts from POSTED payments only (draft/cancelled excluded).
     *
     * @return numeric-string
     */
    private function postedAllocationsTotal(int $invoiceId): string
    {
        $value = DB::table('supplier_payment_allocations')
            ->join('supplier_payments', 'supplier_payments.id', '=', 'supplier_payment_allocations.supplier_payment_id')
            ->where('supplier_payment_allocations.purchase_invoice_id', $invoiceId)
            ->where('supplier_payments.status', SupplierPaymentStatus::POSTED->value)
            ->sum('supplier_payment_allocations.amount');

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
