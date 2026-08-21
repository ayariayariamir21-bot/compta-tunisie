<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CompanyAccountingSetting;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\TaxRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private SalesInvoicePostingService $postingService,
    ) {}

    /**
     * @param  array{company_id: int, customer_id: int, fiscal_year_id: int, accounting_period_id: int, journal_id: int, invoice_date: string, due_date?: string|null, currency: string, payment_terms_days: int, notes?: string|null, terms?: string|null, created_by: int, quote_id?: int|null, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function createDraft(array $data): Invoice
    {
        $companyId = $data['company_id'];
        $this->validateCustomer((int) $data['customer_id'], $companyId);
        $this->validateJournal((int) $data['journal_id'], $companyId);
        $this->validateFiscalYear((int) $data['fiscal_year_id'], $companyId);
        $this->validateAccountingPeriod((int) $data['accounting_period_id'], (int) $data['fiscal_year_id']);
        $this->validatePeriodOpen((int) $data['accounting_period_id']);
        $this->validateInvoiceDateInPeriod($data['invoice_date'], (int) $data['accounting_period_id']);
        $this->validateLines($data['lines'], $companyId);

        $data['invoice_number'] = $this->generateInvoiceNumber($companyId);
        $data['status'] = InvoiceStatus::DRAFT;

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $linesData, $companyId) {
            $invoice = Invoice::create($data);

            $this->syncLines($invoice, $linesData, $companyId);
            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer']);
        });
    }

    /**
     * @param  array{customer_id: int, journal_id: int, invoice_date: string, due_date?: string|null, currency: string, payment_terms_days: int, notes?: string|null, terms?: string|null, lines: array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>}  $data
     */
    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être modifiée.');
        }

        $companyId = $invoice->company_id;
        $this->validateCustomer((int) $data['customer_id'], $companyId);
        $this->validateJournal((int) $data['journal_id'], $companyId);
        $this->validateLines($data['lines'], $companyId);

        $linesData = $data['lines'];
        unset($data['lines']);

        if (($data['due_date'] ?? null) === '') {
            $data['due_date'] = null;
        }

        return DB::transaction(function () use ($invoice, $data, $linesData, $companyId) {
            $invoice->update($data);
            $this->syncLines($invoice, $linesData, $companyId);
            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer']);
        });
    }

    public function post(Invoice $invoice, int $userId): Invoice
    {
        return $this->postingService->post($invoice, $userId);
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être annulée.');
        }

        $invoice->update(['status' => InvoiceStatus::CANCELLED]);

        return $invoice->fresh();
    }

    public function deleteDraft(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException('Seule une facture en brouillon peut être supprimée.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }

    public function createFromQuote(Quote $quote, int $companyId, int $fiscalYearId, int $accountingPeriodId, int $journalId, int $createdBy): Invoice
    {
        if ($quote->status !== QuoteStatus::ACCEPTED) {
            throw new \InvalidArgumentException('Seul un devis accepté peut générer une facture.');
        }

        if ($quote->company_id !== $companyId) {
            throw new \InvalidArgumentException('Le devis n\'appartient pas à cette société.');
        }

        $this->validateCustomer($quote->customer_id, $companyId);
        $this->validateFiscalYear($fiscalYearId, $companyId);
        $this->validateAccountingPeriod($accountingPeriodId, $fiscalYearId);
        $this->validatePeriodOpen($accountingPeriodId);
        $this->validateJournal($journalId, $companyId);

        $originalLines = $quote->lines()->with('product', 'taxRate')->get();

        $data = [
            'company_id' => $companyId,
            'customer_id' => $quote->customer_id,
            'fiscal_year_id' => $fiscalYearId,
            'accounting_period_id' => $accountingPeriodId,
            'journal_id' => $journalId,
            'quote_id' => $quote->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => $quote->valid_until ? $quote->valid_until->toDateString() : null,
            'currency' => $quote->currency,
            'payment_terms_days' => $quote->payment_terms_days,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'created_by' => $createdBy,
            'lines' => $originalLines->map(fn (QuoteLine $line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate_id' => $line->tax_rate_id,
            ])->toArray(),
        ];

        return $this->createDraft($data);
    }

    public function generateInvoiceNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "FAC-{$year}-";
        $maxTries = 10;

        $settings = CompanyAccountingSetting::where('company_id', $companyId)->first();
        if ($settings && $settings->invoice_prefix) {
            $prefix = $settings->invoice_prefix.'-'.$year.'-';
        }

        $maxNumber = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->pluck('invoice_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        if ($settings && $settings->invoice_next_number > $maxNumber) {
            $maxNumber = $settings->invoice_next_number - 1;
        }

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! Invoice::where('company_id', $companyId)->where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro de facture unique après '.$maxTries.' tentatives.');
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

    public function calculateTotals(Invoice $invoice): Invoice
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

    public function validateCustomer(int $customerId, int $companyId): Customer
    {
        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException('Le client sélectionné n\'appartient pas à cette société.');
        }

        if (! $customer->is_active) {
            throw new \InvalidArgumentException('Le client sélectionné est inactif. Veuillez choisir un client actif.');
        }

        return $customer;
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

        if (! $product->is_sellable) {
            throw new \InvalidArgumentException('Le produit/service sélectionné n\'est pas vendable.');
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

    public function validateSalesAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte de vente sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte de vente sélectionné est inactif.');
        }

        return $account;
    }

    public function validateReceivableAccount(int $accountId, int $companyId, int $fiscalYearId): Account
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('Le compte client sélectionné n\'appartient pas à cette société/exercice.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('Le compte client sélectionné est inactif.');
        }

        return $account;
    }

    public function validateJournal(int $journalId, int $companyId): Journal
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

    /**
     * @param  array<int, array{product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null}>  $linesData
     */
    public function validateLines(array $linesData, int $companyId): void
    {
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
    private function syncLines(Invoice $invoice, array $linesData, int $companyId): void
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

            $salesAccountId = $product->sales_account_id;

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
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
                'sales_account_id' => $salesAccountId,
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
