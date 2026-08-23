<?php

use App\Enums\JournalEntryStatus;
use App\Livewire\Reports\VatReport;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\VatReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

vatCounter();

function vatCounter(): int
{
    static $counter = 0;

    return ++$counter;
}

function vatService(): VatReportService
{
    return app(VatReportService::class);
}

/**
 * Post a balanced journal entry: [[Account, debit, credit], ...].
 *
 * @param  array<array{0: Account, 1: string, 2: string}>  $lines
 */
function vatPostEntry($test, string $entryDate, array $lines, JournalEntryStatus $status = JournalEntryStatus::POSTED, ?string $reference = null): JournalEntry
{
    $entry = JournalEntry::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'entry_number' => sprintf('OD-VAT-%06d', vatCounter()),
        'entry_date' => $entryDate,
        'reference' => $reference,
        'description' => 'Rapport TVA test entry',
        'status' => $status,
        'created_by' => $test->user->id,
        'posted_at' => $status === JournalEntryStatus::POSTED ? now() : null,
    ]);

    foreach ($lines as [$account, $debit, $credit]) {
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    return $entry;
}

/**
 * Create a posted sales invoice with one taxable line and its journal entry.
 *
 * Mirrors SalesInvoicePostingService exactly: debit customer TTC,
 * credit sales HT, credit the sales VAT account when tax > 0.
 */
function vatPostSalesInvoice($test, string $date, string $base, string $vat, TaxRate $rate, ?string $number = null): Invoice
{
    $total = bcadd($base, $vat, 3);

    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_number' => $number ?? sprintf('FA-2026-%04d', vatCounter()),
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'product_id' => $test->product->id,
        'description' => 'Vente test',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'discount_amount' => '0.000',
        'tax_rate_id' => $rate->id,
        'tax_code' => $rate->code,
        'tax_rate' => $rate->rate,
        'tax_amount' => $vat,
        'line_subtotal' => $base,
        'line_total' => $total,
        'sales_account_id' => $test->revenue->id,
        'sort_order' => 0,
    ]);

    $lines = [
        [$test->receivable, $total, '0.000'],
        [$test->revenue, '0.000', $base],
    ];

    if (bccomp($vat, '0.000', 3) > 0) {
        $lines[] = [$test->outputVat, '0.000', $vat];
    }

    $entry = vatPostEntry($test, $date, $lines, JournalEntryStatus::POSTED, $invoice->invoice_number);
    $invoice->update(['journal_entry_id' => $entry->id]);

    return $invoice;
}

/**
 * Create a posted sales credit note with one taxable line and its entry.
 *
 * Mirrors SalesCreditNotePostingService: DEBIT sales accounts + DEBIT
 * sales VAT accounts, CREDIT customer receivable TTC.
 */
function vatPostSalesCreditNote($test, string $date, string $base, string $vat, TaxRate $rate, ?int $invoiceId = null): CreditNote
{
    $total = bcadd($base, $vat, 3);

    $creditNote = CreditNote::create([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_id' => $invoiceId,
        'credit_note_number' => sprintf('AV-2026-%04d', vatCounter()),
        'credit_note_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    CreditNoteLine::create([
        'credit_note_id' => $creditNote->id,
        'invoice_line_id' => null,
        'product_id' => $test->product->id,
        'description' => 'Retour test',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'discount_amount' => '0.000',
        'tax_rate_id' => $rate->id,
        'tax_code' => $rate->code,
        'tax_rate' => $rate->rate,
        'tax_amount' => $vat,
        'line_subtotal' => $base,
        'line_total' => $total,
        'sales_account_id' => $test->revenue->id,
        'sort_order' => 0,
    ]);

    $lines = [
        [$test->revenue, $base, '0.000'],
    ];

    if (bccomp($vat, '0.000', 3) > 0) {
        $lines[] = [$test->outputVat, $vat, '0.000'];
    }

    $lines[] = [$test->receivable, '0.000', $total];

    $entry = vatPostEntry($test, $date, $lines, JournalEntryStatus::POSTED, $creditNote->credit_note_number);
    $creditNote->update(['journal_entry_id' => $entry->id]);

    return $creditNote;
}

/**
 * Create a posted purchase invoice with one taxable line and its entry.
 *
 * Mirrors PurchaseInvoicePostingService: debit purchase HT + DEBIT
 * purchase VAT account, credit supplier payable TTC.
 */
function vatPostPurchaseInvoice($test, string $date, string $base, string $vat, TaxRate $rate): PurchaseInvoice
{
    $total = bcadd($base, $vat, 3);

    $purchaseInvoice = PurchaseInvoice::create([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_number' => sprintf('FF-2026-%04d', vatCounter()),
        'supplier_invoice_number' => null,
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    PurchaseInvoiceLine::create([
        'purchase_invoice_id' => $purchaseInvoice->id,
        'product_id' => $test->product->id,
        'description' => 'Achat test',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'discount_amount' => '0.000',
        'tax_rate_id' => $rate->id,
        'tax_code' => $rate->code,
        'tax_rate' => $rate->rate,
        'tax_amount' => $vat,
        'line_subtotal' => $base,
        'line_total' => $total,
        'purchase_account_id' => $test->purchase->id,
        'sort_order' => 0,
    ]);

    $lines = [
        [$test->purchase, $base, '0.000'],
    ];

    if (bccomp($vat, '0.000', 3) > 0) {
        $lines[] = [$test->inputVat, $vat, '0.000'];
    }

    $lines[] = [$test->payable, '0.000', $total];

    $entry = vatPostEntry($test, $date, $lines, JournalEntryStatus::POSTED, $purchaseInvoice->invoice_number);
    $purchaseInvoice->update(['journal_entry_id' => $entry->id]);

    return $purchaseInvoice;
}

/**
 * Create a posted expense with one taxable line and its entry.
 *
 * Mirrors ExpensePostingService: debit expense HT + DEBIT purchase VAT
 * account, credit bank TTC (immediate payment mode).
 */
function vatPostExpense($test, string $date, string $base, string $vat, TaxRate $rate): Expense
{
    $total = bcadd($base, $vat, 3);

    $expense = Expense::create([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'payment_method_id' => null,
        'expense_number' => sprintf('DEP-2026-%04d', vatCounter()),
        'expense_date' => $date,
        'due_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'reference' => null,
        'description' => 'Dépense test TVA',
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    ExpenseLine::create([
        'expense_id' => $expense->id,
        'expense_account_id' => $test->expense->id,
        'label' => 'Charge test',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'gross_amount' => $base,
        'discount_amount' => '0.000',
        'line_subtotal' => $base,
        'tax_rate_id' => $rate->id,
        'tax_code' => $rate->code,
        'tax_rate' => $rate->rate,
        'tax_amount' => $vat,
        'line_total' => $total,
        'sort_order' => 0,
    ]);

    $lines = [
        [$test->expense, $base, '0.000'],
    ];

    if (bccomp($vat, '0.000', 3) > 0) {
        $lines[] = [$test->inputVat, $vat, '0.000'];
    }

    $lines[] = [$test->bank, '0.000', $total];

    $entry = vatPostEntry($test, $date, $lines, JournalEntryStatus::POSTED, $expense->expense_number);
    $expense->update(['journal_entry_id' => $entry->id]);

    return $expense;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->company = Company::create([
        'name' => 'Société TVA Test',
        'legal_name' => 'Société TVA Test SARL',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $this->company->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    // Only January is an open period on purpose: VAT reporting over closed
    // months must work like every other historical report in this app.
    $this->period = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'name' => 'Janvier 2026',
        'code' => '2026-01',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);

    $this->journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'OD',
        'name' => 'Opérations diverses',
        'type' => 'operations_diverses',
        'is_active' => true,
    ]);

    $makeAccount = fn (string $code, string $name, string $type) => Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => $code,
        'name' => $name,
        'account_type' => $type,
        'is_active' => true,
    ]);

    $this->bank = $makeAccount('512000', 'Banque', 'asset');
    $this->receivable = $makeAccount('411000', 'Clients', 'asset');
    $this->payable = $makeAccount('401000', 'Fournisseurs', 'liability');
    $this->revenue = $makeAccount('707000', 'Ventes de marchandises', 'revenue');
    $this->purchase = $makeAccount('607000', 'Achats de marchandises', 'expense');
    $this->expense = $makeAccount('613000', 'Locations et charges locatives', 'expense');
    $this->outputVat = $makeAccount('436710', 'TVA collectée', 'liability');
    $this->outputVat2 = $makeAccount('436720', 'TVA collectée 13%', 'liability');
    $this->inputVat = $makeAccount('436660', 'TVA déductible', 'asset');

    $makeTaxRate = fn (array $attributes) => TaxRate::create(array_merge([
        'company_id' => $this->company->id,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
        'sales_tax_account_id' => $this->outputVat->id,
        'purchase_tax_account_id' => $this->inputVat->id,
    ], $attributes));

    $this->tva19 = $makeTaxRate(['code' => 'TVA19', 'name' => 'TVA 19%', 'rate' => '19.000', 'type' => 'vat']);
    $this->tva13 = $makeTaxRate(['code' => 'TVA13', 'name' => 'TVA 13%', 'rate' => '13.000', 'type' => 'vat', 'sales_tax_account_id' => $this->outputVat2->id]);
    $this->exempt = $makeTaxRate(['code' => 'EXO', 'name' => 'Exonéré', 'rate' => '0.000', 'type' => 'exempt', 'sales_tax_account_id' => null, 'purchase_tax_account_id' => null]);
    $this->zeroRated = $makeTaxRate(['code' => 'TPZ', 'name' => 'Taux zéro', 'rate' => '0.000', 'type' => 'zero_rated', 'sales_tax_account_id' => null, 'purchase_tax_account_id' => null]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CLI001',
        'name' => 'Client SARL',
        'customer_type' => 'company',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'ART001',
        'name' => 'Article test',
        'type' => 'product',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FOU001',
        'name' => 'Fournisseur SARL',
        'supplier_type' => 'company',
        'is_active' => true,
    ]);

    session(['current_company_id' => $this->company->id]);
    session(['current_fiscal_year_id' => $this->fiscalYear->id]);
});

// ---------- Controlled accounting scenarios ----------

it('computes collected VAT net of credit notes per the controlled scenario', function () {
    // §32: SI 1000 HT / 190 TVA then CN -200 / -38 -> net collected 152.
    $invoice = vatPostSalesInvoice($this, '2026-03-05', '1000.000', '190.000', $this->tva19);
    vatPostSalesCreditNote($this, '2026-03-15', '200.000', '38.000', $this->tva19, $invoice->id);

    $service = vatService();
    $report = $service->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('152.000')
        ->and($report['total_output_base'])->toBe('800.000')
        ->and($report['deductible_vat'])->toBe('0.000')
        ->and($report['net_vat'])->toBe('152.000')
        ->and($report['has_payable'])->toBeTrue()
        ->and($report['has_credit'])->toBeFalse();
});

it('computes deductible VAT from purchase invoices and expenses', function () {
    // §33: PI 500/95 + expense 200/38 -> deductible 133.
    vatPostPurchaseInvoice($this, '2026-03-06', '500.000', '95.000', $this->tva19);
    vatPostExpense($this, '2026-03-08', '200.000', '38.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['deductible_vat'])->toBe('133.000')
        ->and($report['total_input_base'])->toBe('700.000')
        ->and($report['collected_vat'])->toBe('0.000');
});

it('computes the controlled full scenario: collected 190, deductible 133, net 57 TVA à payer', function () {
    // §33 chain: SI 1000/190 against PI 500/95 + expense 200/38.
    vatPostSalesInvoice($this, '2026-03-05', '1000.000', '190.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-03-06', '500.000', '95.000', $this->tva19);
    vatPostExpense($this, '2026-03-08', '200.000', '38.000', $this->tva19);

    $service = vatService();
    $report = $service->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $summary = $service->getSummary($report);

    expect($report['collected_vat'])->toBe('190.000')
        ->and($report['total_output_base'])->toBe('1000.000')
        ->and($report['deductible_vat'])->toBe('133.000')
        ->and($report['net_vat'])->toBe('57.000')
        ->and($summary['has_payable'])->toBeTrue()
        ->and($summary['status_label'])->toBe('TVA à payer');
});

it('reports a VAT credit when deductible exceeds collected', function () {
    // §34: collected 100 vs deductible 180 -> net -80 crédit de TVA.
    vatPostSalesInvoice($this, '2026-04-02', '526.316', '100.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-04-03', '800.000', '152.000', $this->tva19);
    vatPostExpense($this, '2026-04-04', '147.368', '28.000', $this->tva19);

    $service = vatService();
    $report = $service->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('100.000')
        ->and($report['deductible_vat'])->toBe('180.000')
        ->and($report['net_vat'])->toBe('-80.000')
        ->and($report['has_credit'])->toBeTrue()
        ->and($report['has_payable'])->toBeFalse()
        ->and($service->getStatusLabel($report['net_vat']))->toBe('Crédit de TVA');
});

it('reports no balance when collected equals deductible', function () {
    vatPostSalesInvoice($this, '2026-05-04', '1000.000', '190.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-05-05', '1000.000', '190.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['net_vat'])->toBe('0.000')
        ->and($report['is_neutral'])->toBeTrue()
        ->and(vatService()->getStatusLabel($report['net_vat']))->toBe('Aucun solde de TVA');
});

// ---------- Status filtering ----------

it('excludes draft documents from totals and details', function () {
    $draftInvoice = vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    $draftInvoice->update(['status' => 'draft']);
    JournalEntry::where('reference', $draftInvoice->invoice_number)->update(['status' => 'draft']);

    $draftPurchase = vatPostPurchaseInvoice($this, '2026-03-11', '500.000', '95.000', $this->tva19);
    $draftPurchase->update(['status' => 'draft']);
    JournalEntry::where('reference', $draftPurchase->invoice_number)->update(['status' => 'draft']);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('0.000')
        ->and($report['deductible_vat'])->toBe('0.000')
        ->and($report['documents'])->toHaveCount(0)
        ->and($report['unattributed_collected'])->toBe('0.000');
});

it('excludes cancelled documents from totals and details', function () {
    $cancelledInvoice = vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    $cancelledInvoice->update(['status' => 'cancelled']);
    // Cancelled documents have their entry cancelled too in real flows.
    JournalEntry::where('reference', $cancelledInvoice->invoice_number)->update(['status' => 'cancelled']);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('0.000')
        ->and($report['documents'])->toHaveCount(0);
});

it('never counts payments as VAT movements', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);

    // A customer payment books bank/receivable only — no VAT account moves.
    vatPostEntry($this, '2026-03-20', [
        [$this->bank, '1190.000', '0.000'],
        [$this->receivable, '0.000', '1190.000'],
    ]);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('190.000')
        ->and($report['deductible_vat'])->toBe('0.000')
        ->and($report['unattributed_collected'])->toBe('0.000');
});

// ---------- Date boundaries ----------

it('includes movements on both boundary dates of the window', function () {
    vatPostSalesInvoice($this, '2026-01-01', '100.000', '19.000', $this->tva19);
    vatPostSalesInvoice($this, '2026-01-31', '200.000', '38.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-01-31');

    expect($report['collected_vat'])->toBe('57.000')
        ->and(count($report['documents']))->toBe(2);
});

it('excludes movements just outside the window', function () {
    vatPostSalesInvoice($this, '2025-12-31', '100.000', '19.000', $this->tva19);
    vatPostSalesInvoice($this, '2026-02-01', '200.000', '38.000', $this->tva19);
    vatPostSalesInvoice($this, '2026-01-15', '400.000', '76.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-01-31');

    expect($report['collected_vat'])->toBe('76.000')
        ->and($report['total_output_base'])->toBe('400.000')
        ->and(count($report['documents']))->toBe(1);
});

// ---------- Rate breakdowns and multi-account support ----------

it('breaks down collected VAT by tax rate across different VAT accounts', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    vatPostSalesInvoice($this, '2026-03-12', '500.000', '65.000', $this->tva13);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('255.000');

    $outputRows = array_values(array_filter($report['tax_rates'], fn (array $row): bool => $row['direction'] === 'collectee'));

    expect($outputRows)->toHaveCount(2);

    $byCode = [];
    foreach ($outputRows as $row) {
        $byCode[$row['code']] = $row;
    }

    expect($byCode['TVA19']['base'])->toBe('1000.000')
        ->and($byCode['TVA19']['vat'])->toBe('190.000')
        ->and($byCode['TVA19']['type_label'])->toBe('TVA')
        ->and($byCode['TVA13']['base'])->toBe('500.000')
        ->and($byCode['TVA13']['vat'])->toBe('65.000')
        ->and(count($report['output_accounts']))->toBe(2)
        ->and(count($report['input_accounts']))->toBe(1);
});

it('keeps sales and purchase sides separate when one rate serves both directions', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-03-11', '600.000', '114.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $rowsByDirection = [
        'collectee' => [],
        'deductible' => [],
    ];

    foreach ($report['tax_rates'] as $row) {
        $rowsByDirection[$row['direction']][] = $row;
    }

    expect(count($rowsByDirection['collectee']))->toBe(1)
        ->and(count($rowsByDirection['deductible']))->toBe(1)
        ->and($rowsByDirection['collectee'][0]['vat'])->toBe('190.000')
        ->and($rowsByDirection['deductible'][0]['vat'])->toBe('114.000')
        ->and($rowsByDirection['deductible'][0]['direction_label'])->toBe('TVA déductible');
});

it('groups several documents sharing one rate into a single breakdown row', function () {
    $invoice = vatPostSalesInvoice($this, '2026-03-10', '300.000', '57.000', $this->tva19);
    vatPostSalesInvoice($this, '2026-03-11', '700.000', '133.000', $this->tva19);
    vatPostSalesCreditNote($this, '2026-03-12', '100.000', '19.000', $this->tva19, $invoice->id);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $outputRows = array_values(array_filter($report['tax_rates'], fn (array $row): bool => $row['direction'] === 'collectee'));

    expect(count($outputRows))->toBe(1)
        ->and($outputRows[0]['base'])->toBe('900.000')
        ->and($outputRows[0]['vat'])->toBe('171.000');
});

it('shows exempt and zero-rated bases without any ledger VAT', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '0.000', $this->exempt);
    vatPostSalesInvoice($this, '2026-03-11', '800.000', '0.000', $this->zeroRated);
    vatPostSalesInvoice($this, '2026-03-12', '500.000', '95.000', $this->tva19);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $outputRows = array_values(array_filter($report['tax_rates'], fn (array $row): bool => $row['direction'] === 'collectee'));

    $byTypeLabel = [];
    foreach ($outputRows as $row) {
        $byTypeLabel[$row['type_label']] = $row;
    }

    expect($report['collected_vat'])->toBe('95.000')
        ->and($report['total_output_base'])->toBe('2300.000')
        ->and($byTypeLabel['Exonéré']['base'])->toBe('1000.000')
        ->and($byTypeLabel['Exonéré']['vat'])->toBe('0.000')
        ->and($byTypeLabel['Taux zéro']['base'])->toBe('800.000')
        ->and($byTypeLabel['Taux zéro']['vat'])->toBe('0.000')
        ->and($byTypeLabel['TVA']['base'])->toBe('500.000')
        ->and($byTypeLabel['TVA']['vat'])->toBe('95.000');
});

// ---------- Document details ----------

it('lists chronological document details with types, numbers and parties', function () {
    $invoice = vatPostSalesInvoice($this, '2026-03-05', '1000.000', '190.000', $this->tva19);
    vatPostSalesCreditNote($this, '2026-03-07', '200.000', '38.000', $this->tva19, $invoice->id);
    vatPostPurchaseInvoice($this, '2026-03-06', '500.000', '95.000', $this->tva19);
    vatPostExpense($this, '2026-03-08', '200.000', '38.000', $this->tva19);

    $details = vatService()->getOutputVatDetails($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect(count($details))->toBe(2)
        ->and($details[0]['document_type_label'])->toBe('Facture de vente')
        ->and($details[0]['party'])->toBe('Client SARL')
        ->and($details[0]['base'])->toBe('1000.000')
        ->and($details[0]['vat'])->toBe('190.000')
        ->and($details[1]['document_type_label'])->toBe('Avoir de vente')
        ->and($details[1]['vat'])->toBe('-38.000');

    $inputDetails = vatService()->getInputVatDetails($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect(count($inputDetails))->toBe(2)
        ->and($inputDetails[0]['document_type_label'])->toBe("Facture d'achat")
        ->and($inputDetails[0]['party'])->toBe('Fournisseur SARL')
        ->and($inputDetails[1]['document_type_label'])->toBe('Dépense fournisseur')
        ->and($inputDetails[1]['vat'])->toBe('38.000');
});

it('returns empty details when no documents carry VAT in the window', function () {
    expect(vatService()->getOutputVatDetails($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31'))->toBe([])
        ->and(vatService()->getInputVatDetails($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31'))->toBe([]);
});

// ---------- Ledger reconciliation and unattributed movements ----------

it('reconciles document-attributed VAT with ledger totals when everything is explained', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-03-11', '500.000', '95.000', $this->tva19);

    $reconciliation = vatService()->reconcileWithLedger($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($reconciliation['ledger_collected'])->toBe('190.000')
        ->and($reconciliation['document_collected'])->toBe('190.000')
        ->and($reconciliation['difference_collected'])->toBe('0.000')
        ->and($reconciliation['ledger_deductible'])->toBe('95.000')
        ->and($reconciliation['document_deductible'])->toBe('95.000')
        ->and($reconciliation['difference_deductible'])->toBe('0.000')
        ->and($reconciliation['is_balanced'])->toBeTrue();
});

it('surfaces manual ledger movements as unattributed VAT instead of discarding them', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);

    // Manual OD entry crediting the output VAT account without any document.
    vatPostEntry($this, '2026-03-20', [
        [$this->bank, '50.000', '0.000'],
        [$this->outputVat, '0.000', '50.000'],
    ]);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('240.000')
        ->and($report['unattributed_collected'])->toBe('50.000');

    $reconciliation = vatService()->reconcileWithLedger($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($reconciliation['difference_collected'])->toBe('50.000')
        ->and($reconciliation['is_balanced'])->toBeFalse();
});

it('surfaces unattributed deductible movements on the input side', function () {
    vatPostPurchaseInvoice($this, '2026-03-10', '500.000', '95.000', $this->tva19);

    vatPostEntry($this, '2026-03-21', [
        [$this->inputVat, '20.000', '0.000'],
        [$this->bank, '0.000', '20.000'],
    ]);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['deductible_vat'])->toBe('115.000')
        ->and($report['unattributed_deductible'])->toBe('20.000')
        ->and($report['unattributed_collected'])->toBe('0.000');
});

it('excludes draft entries sitting on VAT accounts entirely', function () {
    vatPostEntry($this, '2026-03-10', [
        [$this->outputVat, '0.000', '99.000'],
        [$this->bank, '99.000', '0.000'],
    ], JournalEntryStatus::DRAFT);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('0.000')
        ->and($report['unattributed_collected'])->toBe('0.000');
});

it('agrees with the general ledger closing balances of the VAT accounts', function () {
    $invoice = vatPostSalesInvoice($this, '2026-03-05', '1000.000', '190.000', $this->tva19);
    vatPostSalesCreditNote($this, '2026-03-15', '200.000', '38.000', $this->tva19, $invoice->id);
    vatPostPurchaseInvoice($this, '2026-03-06', '500.000', '95.000', $this->tva19);

    $service = vatService();
    $report = $service->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $filters = ['from_date' => '2026-01-01', 'to_date' => '2026-12-31'];

    $outputBalance = app(GeneralLedgerService::class)->calculateClosingBalance(
        $this->outputVat,
        $this->company,
        $this->fiscalYear,
        $filters,
    );

    $inputBalance = app(GeneralLedgerService::class)->calculateClosingBalance(
        $this->inputVat,
        $this->company,
        $this->fiscalYear,
        $filters,
    );

    // GL balance = debits - credits: opposite sign of the report amounts.
    expect($outputBalance)->toBe(bcsub('0.000', $report['collected_vat'], 3))
        ->and($inputBalance)->toBe($report['deductible_vat']);
});

// ---------- Company isolation ----------

it('ignores tax rates and documents belonging to other companies', function () {
    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);

    // Another company whose tax rate points at THIS company's output VAT
    // account must not influence this company's report...
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $companyB->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    TaxRate::create([
        'company_id' => $companyB->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'rate' => '19.000',
        'type' => 'vat',
        'is_active' => true,
        'sales_tax_account_id' => $this->outputVat->id,
        'purchase_tax_account_id' => $this->inputVat->id,
    ]);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['collected_vat'])->toBe('190.000')
        ->and(count($report['output_accounts']))->toBe(2);

    $outputCodes = array_column($report['output_accounts'], 'code');
    expect(in_array('436710', $outputCodes, true))->toBeTrue()
        ->and(in_array('436720', $outputCodes, true))->toBeTrue();

    // ...and company B's own window shows nothing (entries belong to FY A).
    $fyB = FiscalYear::create([
        'company_id' => $companyB->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    expect(vatService()->getCollectedVat($companyB, $fyB, '2026-01-01', '2026-12-31'))->toBe('0.000');
});

// ---------- Context validation ----------

it('rejects an invalid start date format', function () {
    vatService()->validateContext($this->company, $this->fiscalYear, '31/01/2026', '2026-12-31');
})->throws(InvalidArgumentException::class, 'La date de début est invalide.');

it('rejects an invalid end date format', function () {
    vatService()->validateContext($this->company, $this->fiscalYear, '2026-01-01', 'not-a-date');
})->throws(InvalidArgumentException::class, 'La date de fin est invalide.');

it('rejects dates outside the fiscal year', function () {
    vatService()->validateContext($this->company, $this->fiscalYear, '2025-12-01', '2026-12-31');
})->throws(InvalidArgumentException::class, 'Les dates du rapport de TVA doivent être comprises entre le 01/01/2026 et le 31/12/2026.');

it('rejects an inverted date range', function () {
    vatService()->validateContext($this->company, $this->fiscalYear, '2026-06-30', '2026-01-01');
})->throws(InvalidArgumentException::class, 'La date de début doit être antérieure ou égale à la date de fin.');

it('rejects a fiscal year from another company', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);

    vatService()->validateContext($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $this->fiscalYear->company_id = $companyB->id;

    vatService()->validateContext($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
})->throws(InvalidArgumentException::class, 'L\'exercice n\'appartient pas à cette société.');

it('allows reporting windows inside already-closed periods', function () {
    vatPostSalesInvoice($this, '2026-01-15', '1000.000', '190.000', $this->tva19);

    $this->period->update(['is_closed' => true, 'is_open' => false]);

    $report = vatService()->getVatReport($this->company, $this->fiscalYear, '2026-01-01', '2026-01-31');

    expect($report['collected_vat'])->toBe('190.000');
});

// ---------- Pure helpers ----------

it('computes net VAT and status labels through the pure helpers', function () {
    $service = vatService();

    expect($service->getNetVat('152.000', '133.000'))->toBe('19.000')
        ->and($service->getNetVat('100.000', '180.000'))->toBe('-80.000')
        ->and($service->getNetVat('0.000', '0.000'))->toBe('0.000')
        ->and($service->getStatusLabel('19.000'))->toBe('TVA à payer')
        ->and($service->getStatusLabel('-80.000'))->toBe('Crédit de TVA')
        ->and($service->getStatusLabel('0.000'))->toBe('Aucun solde de TVA');
});

// ---------- Livewire page ----------

it('renders the VAT page with default period and French labels', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '12345.000', '2345.550', $this->tva19);

    Livewire::test(VatReport::class)
        ->assertOk()
        ->assertSet('fromDate', '2026-01-01')
        ->assertSet('toDate', fn ($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1)
        ->assertSee('Rapport de TVA')
        ->assertSee('TVA collectée')
        ->assertSee('TVA déductible')
        ->assertSee('TVA nette')
        ->assertSee('(TVA à payer)')
        ->assertSee('12 345,000');
});

it('renders the VAT credit interpretation on the page', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '526.316', '100.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-03-11', '800.000', '152.000', $this->tva19);
    vatPostExpense($this, '2026-03-12', '147.368', '28.000', $this->tva19);

    Livewire::test(VatReport::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-12-31')
        ->assertOk()
        ->assertSee('(Crédit de TVA)');
});

it('shows document detail rows and rate breakdowns on the page', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);
    vatPostPurchaseInvoice($this, '2026-03-11', '500.000', '95.000', $this->tva19);

    Livewire::test(VatReport::class)
        ->assertOk()
        ->assertSee('Détail des documents')
        ->assertSee('Facture de vente')
        ->assertSee("Facture d'achat")
        ->assertSee('Client SARL')
        ->assertSee('Fournisseur SARL')
        ->assertSee('TVA19');
});

it('shows a clear error when the selected period leaves the fiscal year', function () {
    $this->actingAs($this->user);

    Livewire::test(VatReport::class)
        ->set('fromDate', '2027-01-01')
        ->set('toDate', '2026-12-31')
        ->assertOk()
        ->assertSee('Les dates du rapport de TVA doivent être comprises entre le 01/01/2026 et le 31/12/2026.');
});

it('switches the rendered report when the current company changes', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '7777.000', '147.763', $this->tva19);

    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $companyB->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $fyB = FiscalYear::create([
        'company_id' => $companyB->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    session(['current_company_id' => $companyB->id, 'current_fiscal_year_id' => $fyB->id]);

    Livewire::test(VatReport::class)
        ->assertOk()
        ->assertSee('Société B')
        ->assertDontSee('7 777,000');

    session(['current_company_id' => $this->company->id, 'current_fiscal_year_id' => $this->fiscalYear->id]);

    Livewire::test(VatReport::class)
        ->assertOk()
        ->assertSee('7 777,000');
});

it('does not write anything to the database when rendering', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '1000.000', '190.000', $this->tva19);

    $entriesBefore = JournalEntry::count();
    $linesBefore = JournalEntryLine::count();

    Livewire::test(VatReport::class)->assertOk();
    Livewire::test(VatReport::class)->assertOk();

    expect(JournalEntry::count())->toBe($entriesBefore)
        ->and(JournalEntryLine::count())->toBe($linesBefore);
});

it('serves the VAT page through the authenticated route named reports.vat', function () {
    $this->actingAs($this->user);

    vatPostSalesInvoice($this, '2026-03-10', '6000.000', '1140.000', $this->tva19);

    expect(route('reports.vat'))->toBe(url('reports/vat'));

    $response = $this->get(route('reports.vat'));

    $response->assertOk();
    expect(session('current_company_id'))->toBe($this->company->id);

    // Unauthenticated visitors are redirected to the login page.
    auth()->logout();

    $this->get(route('reports.vat'))->assertRedirect();
});
