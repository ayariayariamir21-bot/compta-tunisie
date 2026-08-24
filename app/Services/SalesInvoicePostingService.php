<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\CompanyAccountingSetting;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\JournalEntryService;
use App\Services\Security\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesInvoicePostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    public function post(Invoice $invoice, int $userId): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $invoice->load(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'fiscalYear', 'accountingPeriod', 'journal']);

            $this->validatePreconditions($invoice);

            $receivableAccountId = $this->resolveReceivableAccount($invoice);
            $this->validateReceivableAccount($receivableAccountId, $invoice);

            $this->validateAllSalesAccounts($invoice);

            $journalEntry = $this->createJournalEntry($invoice, $userId);
            $this->createJournalEntryLines($journalEntry, $invoice, $receivableAccountId);

            if (! $journalEntry->fresh()->isBalanced()) {
                throw new \RuntimeException('L\'écriture comptable n\'est pas équilibrée.');
            }

            $journalEntry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);

            $invoice->update([
                'status' => InvoiceStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            $invoice = $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'journalEntry', 'journal', 'fiscalYear', 'accountingPeriod']);

            $this->audits()->logAction(
                AuditAction::InvoicePosted,
                "Facture comptabilisée : {$invoice->invoice_number}.",
                entity: $invoice,
                metadata: ['invoice_number' => $invoice->invoice_number, 'total' => (string) $invoice->total, 'journal_entry_number' => $journalEntry->entry_number],
            );

            return $invoice;
        });
    }

    private function validatePreconditions(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être comptabilisée.');
        }

        if ($invoice->lines()->count() === 0) {
            throw new \InvalidArgumentException('Une facture doit contenir au moins une ligne.');
        }

        if ($invoice->fiscalYear->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé. Impossible de comptabiliser.');
        }

        if ($invoice->accountingPeriod->is_closed || ! $invoice->accountingPeriod->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée. Impossible de comptabiliser.');
        }

        $periodStart = Carbon::parse($invoice->accountingPeriod->start_date);
        $periodEnd = Carbon::parse($invoice->accountingPeriod->end_date);
        $invoiceDate = $invoice->invoice_date;

        if ($invoiceDate->lt($periodStart) || $invoiceDate->gt($periodEnd)) {
            throw new \InvalidArgumentException('La date de la facture doit être comprise dans la période comptable.');
        }
    }

    private function resolveReceivableAccount(Invoice $invoice): int
    {
        if ($invoice->customer->account_id) {
            return $invoice->customer->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $invoice->company_id)->first();
        if ($settings && $settings->default_customer_account_id) {
            return $settings->default_customer_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte client configuré pour ce client. Veuillez assigner un compte de tiers au client ou configurer un compte client par défaut dans les paramètres comptables.');
    }

    private function validateReceivableAccount(int $accountId, Invoice $invoice): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $invoice->company_id)
            ->where('fiscal_year_id', $invoice->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function validateAllSalesAccounts(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if (! $line->sales_account_id) {
                throw new \InvalidArgumentException("La ligne « {$line->description} » n'a pas de compte de vente configuré.");
            }

            Account::where('id', $line->sales_account_id)
                ->where('company_id', $invoice->company_id)
                ->where('fiscal_year_id', $invoice->fiscal_year_id)
                ->where('is_active', true)
                ->firstOrFail();
        }
    }

    private function createJournalEntry(Invoice $invoice, int $userId): JournalEntry
    {
        return JournalEntry::create([
            'company_id' => $invoice->company_id,
            'fiscal_year_id' => $invoice->fiscal_year_id,
            'accounting_period_id' => $invoice->accounting_period_id,
            'journal_id' => $invoice->journal_id,
            'entry_number' => $this->journalEntryService->generateEntryNumber(
                $invoice->company_id,
                $invoice->fiscal_year_id,
                $invoice->journal_id
            ),
            'entry_date' => $invoice->invoice_date,
            'reference' => $invoice->invoice_number,
            'description' => "Facture {$invoice->invoice_number} — {$invoice->customer->name}",
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    private function createJournalEntryLines(JournalEntry $journalEntry, Invoice $invoice, int $receivableAccountId): void
    {
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $receivableAccountId,
            'description' => "Facture {$invoice->invoice_number} — Client: {$invoice->customer->name}",
            'debit' => $invoice->total,
            'credit' => '0.000',
        ]);

        /** @var array<int, numeric-string> $salesAccountTotals */
        $salesAccountTotals = [];
        /** @var array<int, numeric-string> $taxAccountTotals */
        $taxAccountTotals = [];

        foreach ($invoice->lines as $line) {
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
                'description' => "Ventes — Facture {$invoice->invoice_number}",
                'debit' => '0.000',
                'credit' => $amount,
            ]);
        }

        foreach ($taxAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "TVA collectée — Facture {$invoice->invoice_number}",
                'debit' => '0.000',
                'credit' => $amount,
            ]);
        }
    }

    private function resolveTaxAccount(InvoiceLine $line): int
    {
        if ($line->taxRate && $line->taxRate->sales_tax_account_id) {
            return $line->taxRate->sales_tax_account_id;
        }

        throw new \InvalidArgumentException("Aucun compte de TVA collectée n'est configuré pour le taux « {$line->tax_code} ». Veuillez configurer le champ « Compte de vente (TVA) » sur ce taux de taxe.");
    }
}
