<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\JournalEntryService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesCreditNotePostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    public function post(CreditNote $creditNote, int $userId): CreditNote
    {
        return DB::transaction(function () use ($creditNote, $userId) {
            // Row-level locks serialize concurrent postings against the same
            // credit note and the same source invoice.
            /** @var CreditNote $locked */
            $locked = CreditNote::whereKey($creditNote->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();

            $locked->load(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'fiscalYear', 'accountingPeriod', 'journal']);
            $invoice->load('lines');

            $this->validatePreconditions($locked, $invoice);

            // Concurrency gate: credited quantities are recalculated inside
            // the locked transaction. Only POSTED credit notes count towards
            // credited quantities, so a competing draft posting sees the
            // winner's entry and is rejected here.
            $creditedByLineId = DB::table('credit_note_lines')
                ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_lines.credit_note_id')
                ->where('credit_notes.invoice_id', $invoice->id)
                ->where('credit_notes.status', CreditNoteStatus::POSTED->value)
                ->whereIn('credit_note_lines.invoice_line_id', $invoice->lines->modelKeys())
                ->groupBy('credit_note_lines.invoice_line_id')
                ->selectRaw('credit_note_lines.invoice_line_id, COALESCE(SUM(credit_note_lines.quantity), 0) as credited_quantity')
                ->pluck('credited_quantity', 'invoice_line_id');

            foreach ($locked->lines as $line) {
                if (! $line->invoice_line_id) {
                    throw new \InvalidArgumentException("La ligne « {$line->description} » n'est pas liée à une ligne de facture.");
                }

                /** @var InvoiceLine|null $invoiceLine */
                $invoiceLine = $invoice->lines->firstWhere('id', $line->invoice_line_id);
                if ($invoiceLine === null) {
                    throw new \InvalidArgumentException('La ligne créditée ne fait pas partie de la facture source.');
                }

                $creditedRaw = $creditedByLineId[(int) $invoiceLine->id] ?? null;
                $credited = $creditedRaw === null ? '0' : $this->decimalFromDb($creditedRaw);

                $remaining = bcsub((string) $invoiceLine->quantity, $credited, 3);
                if (bccomp($remaining, '0', 3) < 0) {
                    $remaining = '0';
                }

                if (bccomp((string) $line->quantity, $remaining, 3) > 0) {
                    throw new \InvalidArgumentException(
                        'Quantité à créditer supérieure au disponible (disponible : '.$remaining.'). Un autre avoir a peut-être été comptabilisé entre-temps.'
                    );
                }
            }

            $receivableAccountId = $this->resolveReceivableAccount($locked);
            $this->validateReceivableAccount($receivableAccountId, $locked);

            $this->validateAllSalesAccounts($locked);

            $journalEntry = $this->createJournalEntry($locked, $userId);
            $this->createJournalEntryLines($journalEntry, $locked, $receivableAccountId);

            if (! $journalEntry->fresh()->isBalanced()) {
                throw new \RuntimeException('L\'écriture comptable n\'est pas équilibrée.');
            }

            $journalEntry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);

            $locked->update([
                'status' => CreditNoteStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            $locked = $locked->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'journalEntry', 'journal', 'fiscalYear', 'accountingPeriod', 'invoice']);

            $this->audits()->logAction(
                AuditAction::CreditNotePosted,
                "Avoir comptabilisé : {$locked->credit_note_number}.",
                entity: $locked,
                metadata: ['credit_note_number' => $locked->credit_note_number, 'total' => (string) $locked->total, 'journal_entry_number' => $journalEntry->entry_number],
            );

            return $locked;
        });
    }

    private function validatePreconditions(CreditNote $creditNote, Invoice $invoice): void
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un avoir en brouillon peut être comptabilisé.');
        }

        if ($creditNote->company_id !== $invoice->company_id) {
            throw new \InvalidArgumentException('La facture source n\'appartient pas à cette société.');
        }

        if ($invoice->status !== InvoiceStatus::POSTED || ! $invoice->journal_entry_id) {
            throw new \InvalidArgumentException('La facture source doit être comptabilisée.');
        }

        if ($creditNote->lines()->count() === 0) {
            throw new \InvalidArgumentException('Un avoir doit contenir au moins une ligne.');
        }

        if ($creditNote->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé. Impossible de comptabiliser.');
        }

        if ($creditNote->accountingPeriod->is_closed || ! $creditNote->accountingPeriod->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée. Impossible de comptabiliser.');
        }

        // The corrective entry is posted in the CURRENT open period; the
        // source invoice may belong to an earlier period.
        $periodStart = Carbon::parse($creditNote->accountingPeriod->start_date);
        $periodEnd = Carbon::parse($creditNote->accountingPeriod->end_date);
        $creditNoteDate = $creditNote->credit_note_date;

        if ($creditNoteDate->lt($periodStart) || $creditNoteDate->gt($periodEnd)) {
            throw new \InvalidArgumentException('La date de l\'avoir doit être comprise dans la période comptable.');
        }
    }

    private function resolveReceivableAccount(CreditNote $creditNote): int
    {
        if ($creditNote->customer->account_id) {
            return $creditNote->customer->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $creditNote->company_id)->first();
        if ($settings && $settings->default_customer_account_id) {
            return $settings->default_customer_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte client configuré pour ce client. Veuillez assigner un compte de tiers au client ou configurer un compte client par défaut dans les paramètres comptables.');
    }

    private function validateReceivableAccount(int $accountId, CreditNote $creditNote): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $creditNote->company_id)
            ->where('fiscal_year_id', $creditNote->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function validateAllSalesAccounts(CreditNote $creditNote): void
    {
        foreach ($creditNote->lines as $line) {
            if (! $line->sales_account_id) {
                throw new \InvalidArgumentException("La ligne « {$line->description} » n'a pas de compte de vente configuré.");
            }

            Account::where('id', $line->sales_account_id)
                ->where('company_id', $creditNote->company_id)
                ->where('fiscal_year_id', $creditNote->fiscal_year_id)
                ->where('is_active', true)
                ->firstOrFail();
        }
    }

    private function createJournalEntry(CreditNote $creditNote, int $userId): JournalEntry
    {
        return JournalEntry::create([
            'company_id' => $creditNote->company_id,
            'fiscal_year_id' => $creditNote->fiscal_year_id,
            'accounting_period_id' => $creditNote->accounting_period_id,
            'journal_id' => $creditNote->journal_id,
            'entry_number' => $this->journalEntryService->generateEntryNumber(
                $creditNote->company_id,
                $creditNote->fiscal_year_id,
                $creditNote->journal_id
            ),
            'entry_date' => $creditNote->credit_note_date,
            'reference' => $creditNote->credit_note_number,
            'description' => "Avoir {$creditNote->credit_note_number} — {$creditNote->customer->name}",
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    /**
     * Reversed entry compared with a sales invoice:
     * DEBIT sales accounts + DEBIT VAT accounts, CREDIT customer receivable.
     */
    private function createJournalEntryLines(JournalEntry $journalEntry, CreditNote $creditNote, int $receivableAccountId): void
    {
        /** @var array<int, numeric-string> $salesAccountTotals */
        $salesAccountTotals = [];
        /** @var array<int, numeric-string> $taxAccountTotals */
        $taxAccountTotals = [];

        foreach ($creditNote->lines as $line) {
            $salesAccountId = $line->sales_account_id;
            if (! isset($salesAccountTotals[$salesAccountId])) {
                $salesAccountTotals[$salesAccountId] = '0';
            }
            $salesAccountTotals[$salesAccountId] = bcadd($salesAccountTotals[$salesAccountId], (string) $line->line_subtotal, 3);

            if (bccomp((string) $line->tax_amount, '0', 3) > 0) {
                $taxAccountId = $this->resolveTaxAccount($line);
                if (! isset($taxAccountTotals[$taxAccountId])) {
                    $taxAccountTotals[$taxAccountId] = '0';
                }
                $taxAccountTotals[$taxAccountId] = bcadd($taxAccountTotals[$taxAccountId], (string) $line->tax_amount, 3);
            }
        }

        foreach ($salesAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "Retour sur ventes — Avoir {$creditNote->credit_note_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }

        foreach ($taxAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "TVA collectée — Avoir {$creditNote->credit_note_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }

        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $receivableAccountId,
            'description' => "Avoir {$creditNote->credit_note_number} — Client: {$creditNote->customer->name}",
            'debit' => '0.000',
            'credit' => (string) $creditNote->total,
        ]);
    }

    private function resolveTaxAccount(CreditNoteLine $line): int
    {
        if ($line->taxRate && $line->taxRate->sales_tax_account_id) {
            return $line->taxRate->sales_tax_account_id;
        }

        throw new \InvalidArgumentException("Aucun compte de TVA collectée n'est configuré pour le taux « {$line->tax_code} ». Veuillez configurer le champ « Compte de vente (TVA) » sur ce taux de taxe.");
    }

    /**
     * Normalize a database aggregate (SUM over decimal columns) into a
     * fixed 3-decimal numeric string.
     *
     * @return numeric-string
     */
    private function decimalFromDb(mixed $value): string
    {
        return number_format((float) str_replace(',', '', (string) $value), 3, '.', '');
    }
}
