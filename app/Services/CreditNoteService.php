<?php

namespace App\Services;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\AccountingPeriod;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Journal;
use App\Models\Product;
use App\Models\TaxRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    public function __construct(
        private SalesCreditNotePostingService $postingService,
    ) {}

    /**
     * Numbering strategy: AV-{YEAR}-{NNNNNN} scoped per company.
     * The next number is derived from the highest existing number for the
     * current prefix (all statuses included, so numbers are never reused),
     * then uniqueness is verified before returning; the database unique
     * constraint on (company_id, credit_note_number) backstops concurrent
     * generations.
     */
    public function generateCreditNoteNumber(int $companyId): string
    {
        $year = (int) now()->year;
        $prefix = "AV-{$year}-";
        $maxTries = 10;

        $maxNumber = CreditNote::where('company_id', $companyId)
            ->where('credit_note_number', 'like', $prefix.'%')
            ->pluck('credit_note_number')
            ->map(fn (string $num) => (int) substr($num, strlen($prefix)))
            ->filter(fn (int $num) => $num > 0)
            ->max() ?? 0;

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $maxNumber++;
            $candidate = $prefix.str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
            if (! CreditNote::where('company_id', $companyId)->where('credit_note_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un numéro d\'avoir unique après '.$maxTries.' tentatives.');
    }

    /**
     * @param  array{company_id: int, customer_id: int, fiscal_year_id: int, accounting_period_id: int, journal_id: int, invoice_id: int, credit_note_date: string, reason?: string|null, currency?: string, notes?: string|null, created_by: int, lines: array<int, array{invoice_line_id: int, product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null, tax_code?: string|null, tax_rate_value?: string, sales_account_id?: int|null}>}  $data
     */
    public function createDraft(array $data): CreditNote
    {
        $companyId = $data['company_id'];
        $invoice = Invoice::findOrFail((int) $data['invoice_id']);
        $this->validateInvoice($invoice, $companyId);
        $this->validateCustomer((int) $data['customer_id'], $companyId);
        if ($invoice->customer_id !== (int) $data['customer_id']) {
            throw new \InvalidArgumentException('Le client de l\'avoir doit correspondre au client de la facture source.');
        }
        $this->validateJournal((int) $data['journal_id'], $companyId);
        $this->validateFiscalYear((int) $data['fiscal_year_id'], $companyId);
        $this->validateAccountingPeriod((int) $data['accounting_period_id'], (int) $data['fiscal_year_id']);
        $this->validatePeriodOpen((int) $data['accounting_period_id']);
        $this->validateCreditNoteDateInPeriod($data['credit_note_date'], (int) $data['accounting_period_id']);
        $this->validateLines($data['lines'], $invoice);

        $data['credit_note_number'] = $this->generateCreditNoteNumber($companyId);
        $data['status'] = CreditNoteStatus::DRAFT;
        if (($data['currency'] ?? null) === null || $data['currency'] === '') {
            $data['currency'] = 'TND';
        }
        foreach (['reason', 'notes'] as $nullableField) {
            if (($data[$nullableField] ?? null) === '') {
                $data[$nullableField] = null;
            }
        }

        $linesData = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($data, $linesData, $invoice) {
            $creditNote = CreditNote::create($data);

            $this->syncLines($creditNote, $linesData, $invoice);
            $this->calculateTotals($creditNote);

            return $creditNote->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'invoice']);
        });
    }

    /**
     * Create a credit note from a posted invoice.
     *
     * When $lineQuantities is null every remaining quantity is credited (full
     * credit); otherwise it maps invoice_line_id => credit quantity (partial).
     *
     * @param  array<int, string>|null  $lineQuantities
     */
    public function createFromInvoice(
        Invoice $invoice,
        int $companyId,
        int $fiscalYearId,
        int $accountingPeriodId,
        int $journalId,
        int $createdBy,
        string $creditNoteDate,
        ?array $lineQuantities = null,
        ?string $reason = null,
        ?string $notes = null,
    ): CreditNote {
        $remaining = $this->determineRemainingAmounts($invoice);

        $available = array_filter(
            $remaining,
            fn (array $entry): bool => bccomp($entry['remaining_quantity'], '0', 3) > 0
        );

        if ($available === []) {
            throw new \InvalidArgumentException('Cette facture est déjà totalement créditée.');
        }

        $linesData = [];
        foreach ($available as $entry) {
            $line = $entry['line'];
            $quantity = $entry['remaining_quantity'];

            if ($lineQuantities !== null) {
                if (! array_key_exists($line->id, $lineQuantities)) {
                    continue;
                }
                $quantity = $this->toDecimal($lineQuantities[$line->id]);
            }

            $linesData[] = [
                'invoice_line_id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $quantity,
                'unit' => $line->unit,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate_id' => $line->tax_rate_id,
                'tax_code' => $line->tax_code,
                'tax_rate_value' => (string) $line->tax_rate,
                'sales_account_id' => $line->sales_account_id,
            ];
        }

        if ($linesData === []) {
            throw new \InvalidArgumentException('Aucune ligne à créditer : les quantités saisies sont invalides.');
        }

        return $this->createDraft([
            'company_id' => $companyId,
            'customer_id' => $invoice->customer_id,
            'fiscal_year_id' => $fiscalYearId,
            'accounting_period_id' => $accountingPeriodId,
            'journal_id' => $journalId,
            'invoice_id' => $invoice->id,
            'credit_note_date' => $creditNoteDate,
            'reason' => $reason,
            'currency' => $invoice->currency,
            'notes' => $notes,
            'created_by' => $createdBy,
            'lines' => $linesData,
        ]);
    }

    /**
     * @param  array{credit_note_date?: string, reason?: string|null, notes?: string|null, lines?: array<int, array{invoice_line_id: int, quantity: string}>}  $data
     */
    public function updateDraft(CreditNote $creditNote, array $data): CreditNote
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un avoir en brouillon peut être modifié.');
        }

        $invoice = $invoice = Invoice::findOrFail($creditNote->invoice_id);

        return DB::transaction(function () use ($creditNote, $data, $invoice) {
            $updates = [];
            if (isset($data['credit_note_date']) && $data['credit_note_date'] !== '') {
                $this->validateCreditNoteDateInPeriod($data['credit_note_date'], $creditNote->accounting_period_id);
                $updates['credit_note_date'] = $data['credit_note_date'];
            }
            foreach (['reason', 'notes'] as $nullableField) {
                if (array_key_exists($nullableField, $data)) {
                    $updates[$nullableField] = $data[$nullableField] === '' ? null : $data[$nullableField];
                }
            }

            if ($updates !== []) {
                $creditNote->update($updates);
            }

            if (isset($data['lines'])) {
                $existingByInvoiceLine = $creditNote->lines()->get()->keyBy('invoice_line_id');
                $linesData = [];
                foreach ($data['lines'] as $lineInput) {
                    $invoiceLineId = (int) $lineInput['invoice_line_id'];
                    $existing = $existingByInvoiceLine->get($invoiceLineId);
                    if (! $existing) {
                        throw new \InvalidArgumentException('La ligne d\'avoir ne correspond pas à la facture source.');
                    }
                    $linesData[] = [
                        'invoice_line_id' => $invoiceLineId,
                        'product_id' => $existing->product_id,
                        'description' => $existing->description,
                        'quantity' => $this->toDecimal($lineInput['quantity']),
                        'unit' => $existing->unit,
                        'unit_price' => (string) $existing->unit_price,
                        'discount_percent' => (string) $existing->discount_percent,
                        'tax_rate_id' => $existing->tax_rate_id,
                        'tax_code' => $existing->tax_code,
                        'tax_rate_value' => (string) $existing->tax_rate,
                        'sales_account_id' => $existing->sales_account_id,
                    ];
                }

                $creditNote->lines()->delete();
                $this->syncLines($creditNote, $linesData, $invoice);
                $this->calculateTotals($creditNote);
            }

            return $creditNote->fresh(['lines.product', 'lines.taxRate', 'lines.salesAccount', 'customer', 'invoice']);
        });
    }

    public function post(CreditNote $creditNote, int $userId): CreditNote
    {
        return $this->postingService->post($creditNote, $userId);
    }

    public function cancel(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un avoir en brouillon peut être annulé.');
        }

        $creditNote->update(['status' => CreditNoteStatus::CANCELLED]);

        return $creditNote->fresh();
    }

    public function deleteDraft(CreditNote $creditNote): void
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            throw new \InvalidArgumentException('Seul un avoir en brouillon peut être supprimé.');
        }

        DB::transaction(function () use ($creditNote) {
            $creditNote->lines()->delete();
            $creditNote->delete();
        });
    }

    /**
     * Remaining creditable quantity per invoice line.
     *
     * @return array<int, array{line: InvoiceLine, original_quantity: numeric-string, credited_quantity: numeric-string, remaining_quantity: numeric-string}>
     */
    public function determineRemainingAmounts(Invoice $invoice): array
    {
        $lines = $invoice->lines()->orderBy('sort_order')->get();

        $creditedByLineId = DB::table('credit_note_lines')
            ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_lines.credit_note_id')
            ->where('credit_notes.invoice_id', $invoice->id)
            ->where('credit_notes.status', CreditNoteStatus::POSTED->value)
            ->whereIn('credit_note_lines.invoice_line_id', $lines->modelKeys())
            ->groupBy('credit_note_lines.invoice_line_id')
            ->selectRaw('credit_note_lines.invoice_line_id, COALESCE(SUM(credit_note_lines.quantity), 0) as credited_quantity')
            ->pluck('credited_quantity', 'invoice_line_id');

        $result = [];
        foreach ($lines as $line) {
            $original = $this->toDecimal((string) $line->quantity);
            $credited = $this->toDecimal((string) ($creditedByLineId[(int) $line->id] ?? '0'));
            $remaining = bcsub($original, $credited, 3);
            if (bccomp($remaining, '0', 3) < 0) {
                $remaining = '0';
            }

            $result[(int) $line->id] = [
                'line' => $line,
                'original_quantity' => $original,
                'credited_quantity' => $credited,
                'remaining_quantity' => $remaining,
            ];
        }

        return $result;
    }

    public function validateInvoice(Invoice $invoice, int $companyId): void
    {
        if ($invoice->company_id !== $companyId) {
            throw new \InvalidArgumentException('La facture source n\'appartient pas à cette société.');
        }

        if ($invoice->status !== InvoiceStatus::POSTED) {
            throw new \InvalidArgumentException('Seule une facture comptabilisée peut faire l\'objet d\'un avoir.');
        }

        if (! $invoice->journal_entry_id || ! $invoice->journalEntry()->exists()) {
            throw new \InvalidArgumentException('La facture source n\'a pas d\'écriture comptable valide.');
        }
    }

    /**
     * @param  array<int, array{invoice_line_id: int, product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null, tax_code?: string|null, tax_rate_value?: string, sales_account_id?: int|null}>  $linesData
     */
    public function validateLines(array $linesData, Invoice $invoice): void
    {
        if ($linesData === []) {
            throw new \InvalidArgumentException('Un avoir doit contenir au moins une ligne.');
        }

        $remaining = $this->determineRemainingAmounts($invoice);

        $invoiceLineIds = array_map(fn (array $entry): int => (int) $entry['line']->id, $remaining);

        foreach ($linesData as $lineData) {
            $invoiceLineId = (int) $lineData['invoice_line_id'];
            if (! in_array($invoiceLineId, $invoiceLineIds, true)) {
                throw new \InvalidArgumentException('La ligne créditée ne fait pas partie de la facture source.');
            }

            $quantity = $this->toDecimal($lineData['quantity']);
            $price = $this->toDecimal($lineData['unit_price']);
            $discount = $this->toDecimal($lineData['discount_percent']);

            if (bccomp($quantity, '0', 3) <= 0) {
                throw new \InvalidArgumentException('La quantité à créditer doit être strictement positive.');
            }

            $availableQuantity = $remaining[$invoiceLineId]['remaining_quantity'];
            if (bccomp($quantity, $availableQuantity, 3) > 0) {
                throw new \InvalidArgumentException(
                    'Quantité à créditer supérieure au disponible (disponible : '.$availableQuantity.').'
                );
            }

            if (bccomp($price, '0', 3) < 0) {
                throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif.');
            }

            if (bccomp($discount, '0', 3) < 0 || bccomp($discount, '100', 3) > 0) {
                throw new \InvalidArgumentException('Le pourcentage de remise doit être compris entre 0 et 100.');
            }

            $this->validateProduct((int) $lineData['product_id'], $invoice->company_id);

            if (! empty($lineData['tax_rate_id'])) {
                $this->validateTaxRate((int) $lineData['tax_rate_id'], $invoice->company_id);
            }
        }
    }

    /**
     * Historical values are used for pricing and tax; only company ownership
     * of the referenced master data is enforced (activation state is ignored
     * so that crediting remains possible after a product or tax rate has
     * been deactivated since the original invoice).
     */
    public function validateProduct(int $productId, int $companyId): Product
    {
        $product = Product::where('id', $productId)
            ->where('company_id', $companyId)
            ->first();

        if (! $product) {
            throw new \InvalidArgumentException('Le produit/service référencé n\'appartient pas à cette société.');
        }

        return $product;
    }

    public function validateTaxRate(int $taxRateId, int $companyId): TaxRate
    {
        $taxRate = TaxRate::where('id', $taxRateId)
            ->where('company_id', $companyId)
            ->first();

        if (! $taxRate) {
            throw new \InvalidArgumentException('Le taux de TVA référencé n\'appartient pas à cette société.');
        }

        return $taxRate;
    }

    public function validateCustomer(int $customerId, int $companyId): Customer
    {
        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException('Le client sélectionné n\'appartient pas à cette société.');
        }

        return $customer;
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

    public function validateCreditNoteDateInPeriod(string $creditNoteDate, int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $date = Carbon::parse($creditNoteDate);

        $startDate = Carbon::parse($period->start_date);
        $endDate = Carbon::parse($period->end_date);

        if ($date->lt($startDate) || $date->gt($endDate)) {
            throw new \InvalidArgumentException('La date de l\'avoir doit être comprise dans la période comptable (du '.$startDate->format('d/m/Y').' au '.$endDate->format('d/m/Y').').');
        }
    }

    /**
     * BCMath calculation using the HISTORICAL tax rate value stored on the
     * source invoice line — never recomputed from current TaxRate settings.
     *
     * @return array{quantity: numeric-string, unit_price: numeric-string, discount_percent: numeric-string, discount_amount: numeric-string, tax_amount: numeric-string, line_subtotal: numeric-string, line_total: numeric-string}
     */
    public function calculateLine(string $quantity, string $unitPrice, string $discountPercent, string $taxRateValue): array
    {
        bcscale(3);

        $qty = $this->toDecimal($quantity);
        $price = $this->toDecimal($unitPrice);
        $discountPct = $this->toDecimal($discountPercent);

        $gross = bcmul($qty, $price, 3);
        $discountAmount = bcdiv(bcmul($gross, $discountPct, 3), '100', 3);
        $lineSubtotal = bcsub($gross, $discountAmount, 3);

        $taxRate = $this->toDecimal($taxRateValue);
        $taxAmount = bcdiv(bcmul($lineSubtotal, $taxRate, 3), '100', 3);
        $lineTotal = bcadd($lineSubtotal, $taxAmount, 3);

        return [
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_percent' => $discountPct,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }

    public function calculateTotals(CreditNote $creditNote): CreditNote
    {
        $lines = $creditNote->lines()->get();

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

        $creditNote->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);

        return $creditNote->fresh();
    }

    /**
     * @param  array<int, array{invoice_line_id: int, product_id: int, description: string, quantity: string, unit: string, unit_price: string, discount_percent: string, tax_rate_id?: int|null, tax_code?: string|null, tax_rate_value?: string, sales_account_id?: int|null}>  $linesData
     */
    private function syncLines(CreditNote $creditNote, array $linesData, Invoice $invoice): void
    {
        $invoiceLines = $invoice->lines()->get()->keyBy('id');

        foreach ($linesData as $index => $lineData) {
            $invoiceLine = $invoiceLines->get((int) $lineData['invoice_line_id']);
            if (! $invoiceLine) {
                throw new \InvalidArgumentException('La ligne créditée ne fait pas partie de la facture source.');
            }

            $product = $this->validateProduct((int) $lineData['product_id'], $invoice->company_id);

            $calculated = $this->calculateLine(
                $lineData['quantity'],
                $lineData['unit_price'],
                $lineData['discount_percent'],
                $lineData['tax_rate_value'] ?? '0'
            );

            // Historical sales account priority: invoice line, then product default.
            $salesAccountId = $lineData['sales_account_id']
                ?? $invoiceLine->sales_account_id
                ?? $product->sales_account_id;

            CreditNoteLine::create([
                'credit_note_id' => $creditNote->id,
                'invoice_line_id' => $invoiceLine->id,
                'product_id' => $lineData['product_id'],
                'description' => $lineData['description'],
                'quantity' => $calculated['quantity'],
                'unit' => $lineData['unit'],
                'unit_price' => $calculated['unit_price'],
                'discount_percent' => $calculated['discount_percent'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_rate_id' => $lineData['tax_rate_id'] ?? null,
                'tax_code' => $lineData['tax_code'] ?? null,
                'tax_rate' => $this->toDecimal($lineData['tax_rate_value'] ?? '0'),
                'tax_amount' => $calculated['tax_amount'],
                'line_subtotal' => $calculated['line_subtotal'],
                'line_total' => $calculated['line_total'],
                'sales_account_id' => $salesAccountId,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
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
