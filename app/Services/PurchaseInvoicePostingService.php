<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalType;
use App\Enums\NotificationSeverity;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Notifications\BusinessNotification;
use App\Services\Accounting\JournalEntryService;
use App\Services\Security\AuditLogService;
use App\Services\Security\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseInvoicePostingService
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private ?AuditLogService $auditLog = null,
        private ?NotificationService $notifications = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    private function notifications(): NotificationService
    {
        return $this->notifications ?? new NotificationService;
    }

    public function post(PurchaseInvoice $invoice, int $userId): PurchaseInvoice
    {
        $posted = DB::transaction(function () use ($invoice, $userId) {
            $invoice->load(['lines.product', 'lines.taxRate', 'supplier', 'fiscalYear', 'accountingPeriod', 'journal']);

            $this->validatePreconditions($invoice);

            $payableAccountId = $this->resolvePayableAccount($invoice);
            $this->validatePayableAccountId($payableAccountId, $invoice);

            /** @var array<int, int> $purchaseAccountIds */
            $purchaseAccountIds = [];

            foreach ($invoice->lines as $line) {
                $purchaseAccountIds[$line->id] = $this->resolvePurchaseAccountId($line, $invoice->company_id);
            }

            foreach (array_unique($purchaseAccountIds) as $accountId) {
                $this->validatePurchaseAccountId($accountId, $invoice);
            }

            $journalEntry = $this->createJournalEntry($invoice, $userId);
            $this->createJournalEntryLines($journalEntry, $invoice, $payableAccountId, $purchaseAccountIds);

            if (! $journalEntry->fresh()->isBalanced()) {
                throw new \RuntimeException('L\'écriture comptable n\'est pas équilibrée.');
            }

            $journalEntry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
            ]);

            $invoice->update([
                'status' => PurchaseInvoiceStatus::POSTED,
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            $invoice = $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.purchaseAccount', 'supplier', 'journalEntry', 'journal', 'fiscalYear', 'accountingPeriod']);

            $this->audits()->logAction(
                AuditAction::PurchaseInvoicePosted,
                "Facture d'achat comptabilisée : {$invoice->invoice_number}.",
                entity: $invoice,
                metadata: ['invoice_number' => $invoice->invoice_number, 'total' => (string) $invoice->total, 'journal_entry_number' => $journalEntry->entry_number],
            );

            return $invoice;
        });

        // Delivered after the accounting transaction commits.
        $this->notifications()->notifyCompanyRoles(
            Company::query()->findOrFail($posted->company_id),
            CompanyRole::operationalRoles(),
            new BusinessNotification(
                title: "Facture d'achat comptabilisée",
                message: "La facture d'achat {$posted->invoice_number} ({$posted->supplier->name}) a été comptabilisée.",
                severity: NotificationSeverity::Success,
                dedupKey: "purchase_invoice_posted.{$posted->id}",
                companyId: $posted->company_id,
                entityType: 'purchase_invoice',
                entityId: $posted->id,
                routeName: 'purchase-invoices.show',
                routeParams: ['purchaseInvoiceId' => $posted->id],
            ),
            exceptUserId: $userId,
        );

        return $posted;
    }

    private function validatePreconditions(PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== PurchaseInvoiceStatus::DRAFT) {
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

        if ($invoice->journal->type !== JournalType::ACHATS) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'est pas un journal d\'achats.');
        }

        $periodStart = Carbon::parse($invoice->accountingPeriod->start_date);
        $periodEnd = Carbon::parse($invoice->accountingPeriod->end_date);
        $invoiceDate = $invoice->invoice_date;

        if ($invoiceDate->lt($periodStart) || $invoiceDate->gt($periodEnd)) {
            throw new \InvalidArgumentException('La date de la facture doit être comprise dans la période comptable.');
        }
    }

    private function resolvePayableAccount(PurchaseInvoice $invoice): int
    {
        if ($invoice->supplier->account_id) {
            return $invoice->supplier->account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $invoice->company_id)->first();
        if ($settings && $settings->default_supplier_account_id) {
            return $settings->default_supplier_account_id;
        }

        throw new \InvalidArgumentException('Aucun compte fournisseur configuré pour ce fournisseur. Veuillez assigner un compte de tiers au fournisseur ou configurer un compte fournisseur par défaut dans les paramètres comptables.');
    }

    private function validatePayableAccountId(int $accountId, PurchaseInvoice $invoice): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $invoice->company_id)
            ->where('fiscal_year_id', $invoice->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolvePurchaseAccountId(PurchaseInvoiceLine $line, int $companyId): int
    {
        if ($line->product && $line->product->purchase_account_id) {
            return $line->product->purchase_account_id;
        }

        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();
        if ($settings && $settings->default_purchase_account_id) {
            return $settings->default_purchase_account_id;
        }

        throw new \InvalidArgumentException("Aucun compte d'achat configuré pour le produit « {$line->description} ». Veuillez assigner un compte d'achat au produit ou configurer un compte d'achat par défaut dans les paramètres comptables.");
    }

    private function validatePurchaseAccountId(int $accountId, PurchaseInvoice $invoice): void
    {
        Account::where('id', $accountId)
            ->where('company_id', $invoice->company_id)
            ->where('fiscal_year_id', $invoice->fiscal_year_id)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function createJournalEntry(PurchaseInvoice $invoice, int $userId): JournalEntry
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
            'description' => "Facture fournisseur {$invoice->invoice_number} — {$invoice->supplier->name}",
            'status' => JournalEntryStatus::DRAFT,
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array<int, int>  $purchaseAccountIds  line id => purchase account id
     */
    private function createJournalEntryLines(JournalEntry $journalEntry, PurchaseInvoice $invoice, int $payableAccountId, array $purchaseAccountIds): void
    {
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $payableAccountId,
            'description' => "Facture fournisseur {$invoice->invoice_number} — Fournisseur: {$invoice->supplier->name}",
            'debit' => '0.000',
            'credit' => $invoice->total,
        ]);

        /** @var array<int, numeric-string> $purchaseAccountTotals */
        $purchaseAccountTotals = [];
        /** @var array<int, numeric-string> $taxAccountTotals */
        $taxAccountTotals = [];

        foreach ($invoice->lines as $line) {
            $purchaseAccountId = $purchaseAccountIds[$line->id];
            if (! isset($purchaseAccountTotals[$purchaseAccountId])) {
                $purchaseAccountTotals[$purchaseAccountId] = '0';
            }
            $purchaseAccountTotals[$purchaseAccountId] = bcadd($purchaseAccountTotals[$purchaseAccountId], (string) $line->line_subtotal, 3);

            if (bccomp((string) $line->tax_amount, '0', 3) > 0) {
                $taxAccountId = $this->resolveTaxAccountId($line);
                if (! isset($taxAccountTotals[$taxAccountId])) {
                    $taxAccountTotals[$taxAccountId] = '0';
                }
                $taxAccountTotals[$taxAccountId] = bcadd($taxAccountTotals[$taxAccountId], (string) $line->tax_amount, 3);
            }
        }

        foreach ($purchaseAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "Achats — Facture fournisseur {$invoice->invoice_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }

        foreach ($taxAccountTotals as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accountId,
                'description' => "TVA récupérable — Facture fournisseur {$invoice->invoice_number}",
                'debit' => $amount,
                'credit' => '0.000',
            ]);
        }
    }

    private function resolveTaxAccountId(PurchaseInvoiceLine $line): int
    {
        if ($line->taxRate && $line->taxRate->purchase_tax_account_id) {
            return $line->taxRate->purchase_tax_account_id;
        }

        throw new \InvalidArgumentException("Aucun compte de TVA récupérable n'est configuré pour le taux « {$line->tax_code} ». Veuillez configurer le champ « Compte d'achat (TVA) » sur ce taux de taxe.");
    }
}
