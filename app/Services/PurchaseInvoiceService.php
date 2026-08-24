<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\JournalType;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CompanyAccountingSetting;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Security\AuditLogService;
use App\Services\Security\AuditLogService as SecurityAuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(
        private PurchaseInvoicePostingService $postingService,
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): SecurityAuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    /**
     * @param  array{company_id: int, supplier_id: int, fiscal_year_id: int, accounting_period_id: int, journal_id: int, invoice_date: string, due_date?: string|null, supplier_invoice_number?: string|null, currency?: string, payment_terms_days?: int, notes?: string|null, created_by: int, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function createDraft(array $data): PurchaseInvoice
    {
        $companyId = $data['company_id'];
        $this->validateSupplier((int) $data['supplier_id'], $companyId);
        $this->validatePurchaseJournal((int) $data['journal_id'], $companyId);
        $this->validateFiscalYear((int) $data['fiscal_year_id'], $companyId);
        $this->validateAccountingPeriod((int) $data['accounting_period_id'], (int) $data['fiscal_year_id']);
        $this->validatePeriodOpen((int) $data['accounting_period_id']);
        $this->validateInvoiceDateInPeriod($data['invoice_date'], (int) $data['accounting_period_id']);
        $this->validateSupplierInvoiceNumber($companyId, (int) $data['supplier_id'], $data['supplier_invoice_number'] ?? null, null);
        $this->validateLines($data['lines'], $companyId);

        $data['invoice_number'] = $this->generateInvoiceNumber($companyId);
        $data['status'] = PurchaseInvoiceStatus::DRAFT;
        $data['currency'] = $this->resolveCurrency($companyId);

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        if (empty($data['due_date'])) {
            $data['due_date'] = Carbon::parse($data['invoice_date'])
                ->addDays((int) ($data['payment_terms_days'] ?? 0))
                ->toDateString();
        }

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $linesData, $companyId) {
            $invoice = PurchaseInvoice::create($data);

            $this->syncLines($invoice, $linesData, $companyId);
            $this->calculateTotals($invoice);

            $invoice = $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.purchaseAccount', 'supplier']);

            $this->audits()->logModelCreated($invoice, AuditAction::PurchaseInvoiceCreated);

            return $invoice;
        });
    }

    /**
     * @param  array{supplier_id: int, journal_id: int, invoice_date: string, due_date?: string|null, supplier_invoice_number?: string|null, payment_terms_days?: int, notes?: string|null, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function updateDraft(PurchaseInvoice $invoice, array $data): PurchaseInvoice
    {
        if ($invoice->status !== PurchaseInvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être modifiée.');
        }

        $companyId = $invoice->company_id;
        $this->validateSupplier((int) $data['supplier_id'], $companyId);
        $this->validatePurchaseJournal((int) $data['journal_id'], $companyId);
        $this->validateSupplierInvoiceNumber($companyId, (int) $data['supplier_id'], $data['supplier_invoice_number'] ?? null, $invoice->id);
        $this->validateLines($data['lines'], $companyId);

        $linesData = $data['lines'];
        unset($data['lines']);

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        if (empty($data['due_date'])) {
            $data['due_date'] = Carbon::parse($data['invoice_date'])
                ->addDays((int) ($data['payment_terms_days'] ?? 0))
                ->toDateString();
        }

        return DB::transaction(function () use ($invoice, $data, $linesData, $companyId) {
            $invoice->update($data);
            $this->syncLines($invoice, $linesData, $companyId);
            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.purchaseAccount', 'supplier']);
        });
    }

    public function post(PurchaseInvoice $invoice, int $userId): PurchaseInvoice
    {
        return $this->postingService->post($invoice, $userId);
    }

    public function cancel(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status !== PurchaseInvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être annulée.');
        }

        $invoice->update(['status' => PurchaseInvoiceStatus::CANCELLED]);

        return $invoice->fresh();
    }

    public function deleteDraft(PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== PurchaseInvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être supprimée.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }

    public function generateInvoiceNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "ACH-{$year}-";
        $maxTries = 10;

        $maxNumber = PurchaseInvoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->pluck('invoice_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! PurchaseInvoice::where('company_id', $companyId)->where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de facture fournisseur unique après '.$maxTries.' tentatives.');
    }

    /**
     * @return array{quantity: string, unit_price: string, discount_percent: string, discount_amount: string, tax_rate_value: string, tax_amount: string, line_subtotal: string, line_total: string}
     */
    public function calculateLine(string $quantity, string $unitPrice, string $discountPercent, ?TaxRate $taxRate): array
    {
        bcscale(3);

        $qty = $this->toDecimal($quantity);
        $price = $this->toDecimal($unitPrice);
        $discountPct = $this->toDecimal($discountPercent);

        $gross = bcmul($qty, $price, 3);
        $discountAmount = bcdiv(bcmul($gross, $discountPct, 3), '100', 3);
        $lineSubtotal = bcsub($gross, $discountAmount, 3);

        $taxRateValue = $taxRate ? $this->toDecimal((string) $taxRate->rate) : '0';
        $taxAmount = bcdiv(bcmul($lineSubtotal, $taxRateValue, 3), '100', 3);
        $lineTotal = bcadd($lineSubtotal, $taxAmount, 3);

        return [
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_percent' => $discountPct,
            'discount_amount' => $discountAmount,
            'tax_rate_value' => $taxRateValue,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }

    public function calculateTotals(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $lines = $invoice->lines()->get();

        $subtotal = '0';
        $discountTotal = '0';
        $taxTotal = '0';
        $total = '0';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 3);
            $discountTotal = bcadd($discountTotal, (string) $line->discount_amount, 3);
            $taxTotal = bcadd($taxTotal, (string) $line->tax_amount, 3);
            $total = bcadd($total, (string) $line->line_total, 3);
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        return $invoice->fresh();
    }

    public function validateSupplier(int $supplierId, int $companyId): Supplier
    {
        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $companyId)
            ->first();

        if (! $supplier) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné n\'appartient pas à cette société.');
        }

        if (! $supplier->is_active) {
            throw new \InvalidArgumentException('Le fournisseur sélectionné est inactif. Veuillez choisir un fournisseur actif.');
        }

        return $supplier;
    }

    public function validateProduct(int $productId, int $companyId): Product
    {
        $product = Product::where('id', $productId)
            ->where('company_id', $companyId)
            ->first();

        if (! $product) {
            throw new \InvalidArgumentException('Le produit/service sélectionné n\'appartient pas à cette société.');
        }

        if (! $product->is_active) {
            throw new \InvalidArgumentException('Le produit/service sélectionné est inactif.');
        }

        if (! $product->is_purchasable) {
            throw new \InvalidArgumentException('Le produit/service sélectionné n\'est pas achetable.');
        }

        return $product;
    }

    public function validateTaxRate(int $taxRateId, int $companyId): TaxRate
    {
        $taxRate = TaxRate::where('id', $taxRateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $taxRate) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné n\'appartient pas à cette société.');
        }

        if (! $taxRate->is_active) {
            throw new \InvalidArgumentException('Le taux de TVA sélectionné est inactif.');
        }

        return $taxRate;
    }

    public function validatePurchaseJournal(int $journalId, int $companyId): Journal
    {
        $journal = Journal::where('id', $journalId)
            ->where('company_id', $companyId)
            ->first();

        if (! $journal) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'appartient pas à cette société.');
        }

        if (! $journal->is_active) {
            throw new \InvalidArgumentException('Le journal sélectionné est inactif.');
        }

        if ($journal->type !== JournalType::ACHATS) {
            throw new \InvalidArgumentException('Le journal sélectionné n\'est pas un journal d\'achats.');
        }

        return $journal;
    }

    public function validateFiscalYear(int $fiscalYearId, int $companyId): FiscalYear
    {
        $fy = FiscalYear::where('id', $fiscalYearId)
            ->where('company_id', $companyId)
            ->first();

        if (! $fy) {
            throw new \InvalidArgumentException('L\'exercice comptable sélectionné n\'appartient pas à cette société.');
        }

        if ($fy->is_closed) {
            throw new \InvalidArgumentException('L\'exercice comptable est clôturé.');
        }

        return $fy;
    }

    public function validateAccountingPeriod(int $periodId, int $fiscalYearId): AccountingPeriod
    {
        $period = AccountingPeriod::where('id', $periodId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $period) {
            throw new \InvalidArgumentException('La période comptable sélectionnée n\'appartient pas à cet exercice.');
        }

        return $period;
    }

    public function validatePeriodOpen(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->is_closed || ! $period->is_open) {
            throw new \InvalidArgumentException('La période comptable est clôturée.');
        }
    }

    public function validateInvoiceDateInPeriod(string $invoiceDate, int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $date = Carbon::parse($invoiceDate);

        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        if ($date->lt($startDate) || $date->gt($endDate)) {
            throw new \InvalidArgumentException('La date de la facture doit être comprise dans la période comptable (du '.$startDate->format('d/m/Y').' au '.$endDate->format('d/m/Y').').');
        }
    }

    public function validatePurchaseAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte d\'achat sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte d\'achat sélectionné est inactif.');
        }

        return $account;
    }

    public function validatePayableAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte fournisseur sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte fournisseur sélectionné est inactif.');
        }

        return $account;
    }

    private function validateSupplierInvoiceNumber(int $companyId, int $supplierId, ?string $supplierInvoiceNumber, ?int $ignoreId): void
    {
        $number = trim((string) ($supplierInvoiceNumber ?? ''));

        if ($number === '') {
            return;
        }

        $query = PurchaseInvoice::where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('supplier_invoice_number', $number);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Une facture avec ce numéro fournisseur existe déjà pour ce fournisseur.');
        }
    }

    private function resolveCurrency(int $companyId): string
    {
        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();

        return ($settings && $settings->default_currency) ? strtoupper($settings->default_currency) : 'TND';
    }

    /**
     * @param  array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    public function validateLines(array $linesData, int $companyId): void
    {
        if (count($linesData) === 0) {
            throw new \InvalidArgumentException('La facture doit contenir au moins une ligne.');
        }

        foreach ($linesData as $lineData) {
            $qty = $this->toDecimal($lineData['quantity']);
            $price = $this->toDecimal($lineData['unit_price']);
            $discount = $this->toDecimal($lineData['discount_percent']);

            if (bccomp($qty, '0', 3) <= 0) {
                throw new \InvalidArgumentException('La quantité doit être supérieure à zéro.');
            }

            if (bccomp($price, '0', 3) < 0) {
                throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif.');
            }

            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, '100', 3) > 0) {
                throw new \InvalidArgumentException('Le pourcentage de remise doit être compris entre 0 et 100.');
            }

            $this->validateProduct((int) $lineData['product_id'], $companyId);

            if (! empty($lineData['tax_rate_id'])) {
                $this->validateTaxRate((int) $lineData['tax_rate_id'], $companyId);
            }
        }
    }

    /**
     * @param  array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    private function syncLines(PurchaseInvoice $invoice, array $linesData, int $companyId): void
    {
        $invoice->lines()->delete();

        foreach ($linesData as $index => $lineData) {
            $product = $this->validateProduct((int) $lineData['product_id'], $companyId);

            $taxRate = null;
            if (! empty($lineData['tax_rate_id'])) {
                $taxRate = $this->validateTaxRate((int) $lineData['tax_rate_id'], $companyId);
            }

            $calculated = $this->calculateLine(
                $lineData['quantity'],
                $lineData['unit_price'],
                $lineData['discount_percent'],
                $taxRate
            );

            PurchaseInvoiceLine::create([
                'purchase_invoice_id' => $invoice->id,
                'product_id' => $lineData['product_id'],
                'description' => $lineData['description'],
                'quantity' => $calculated['quantity'],
                'unit' => $lineData['unit'],
                'unit_price' => $calculated['unit_price'],
                'discount_percent' => $calculated['discount_percent'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_rate_id' => $lineData['tax_rate_id'] ?? null,
                'tax_code' => $taxRate ? $taxRate->code : null,
                'tax_rate' => $calculated['tax_rate_value'],
                'tax_amount' => $calculated['tax_amount'],
                'line_subtotal' => $calculated['line_subtotal'],
                'line_total' => $calculated['line_total'],
                'purchase_account_id' => $product->purchase_account_id,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Convert a string to a decimal with 3 decimal places.
     *
     * @return numeric-string
     */
    public function toDecimal(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '0';
        }

        return number_format((float) $normalized, 3, '.', '');
    }
}
