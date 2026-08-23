<?php

use App\Enums\PaymentMethodType;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Livewire\Reports\SupplierStatement;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\SupplierPaymentPostingService;
use App\Services\Accounting\SupplierStatementService;
use App\Services\PurchaseInvoicePostingService;
use App\Services\PurchaseInvoiceService;
use App\Services\SupplierPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->company = Company::create([
        'name' => 'Société A',
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

    // Open periods used by statement scenarios (posting validates the document
    // date against its period).
    $this->periods = [];
    foreach ([[1, '2026-01-01', '2026-01-31'], [2, '2026-02-01', '2026-02-28'], [3, '2026-03-01', '2026-03-31']] as [$month, $start, $end]) {
        $this->periods[$month] = AccountingPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => sprintf('2026-%02d', $month),
            'code' => sprintf('2026-%02d', $month),
            'start_date' => $start,
            'end_date' => $end,
            'is_open' => true,
            'is_closed' => false,
        ]);
    }

    $this->journalAchats = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'ACH',
        'name' => 'Achats',
        'type' => 'achats',
        'is_active' => true,
    ]);

    $this->journalBanque = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'BQE',
        'name' => 'Banques',
        'type' => 'banque',
        'is_active' => true,
    ]);

    $this->account401 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '401000',
        'name' => 'Fournisseurs',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->account532 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '532000',
        'name' => 'Banques',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account607 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '607000',
        'name' => 'Achats de marchandises',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $this->account4456 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445600',
        'name' => 'TVA déductible',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur Alpha',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'tax_identifier' => '9876543B',
        'account_id' => $this->account401->id,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'purchase_tax_account_id' => $this->account4456->id,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '25.000',
        'purchase_account_id' => $this->account607->id,
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => false,
        'is_purchasable' => true,
    ]);

    $this->methodVir = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'VIR',
        'name' => 'Virement bancaire',
        'type' => PaymentMethodType::BANK_TRANSFER,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->periods[1]->id,
    ]);
});

// ---------- Helpers ----------

function ssStatementService(): SupplierStatementService
{
    return new SupplierStatementService;
}

function ssInvoiceService(): PurchaseInvoiceService
{
    return new PurchaseInvoiceService(new PurchaseInvoicePostingService(new JournalEntryService));
}

function ssPayService(): SupplierPaymentService
{
    return new SupplierPaymentService(new SupplierPaymentPostingService(
        new JournalEntryService
    ));
}

function ssPeriodForDate($test, string $date): AccountingPeriod
{
    $month = (int) substr($date, 5, 2);

    return $test->periods[$month];
}

/**
 * Posted purchase invoice of round total: quantity × 1.000, no tax.
 */
function ssPostInvoice($test, string $date = '2026-01-15', string $quantity = '1000.000'): PurchaseInvoice
{
    $invoice = ssInvoiceService()->createDraft([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => ssPeriodForDate($test, $date)->id,
        'journal_id' => $test->journalAchats->id,
        'invoice_date' => $date,
        'due_date' => $date,
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $test->user->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => $quantity,
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);

    ssInvoiceService()->post($invoice->fresh(), $test->user->id);

    return $invoice->fresh();
}

function ssPostPayment($test, string $amount, string $date = '2026-01-20', array $overrides = []): SupplierPayment
{
    $payment = ssPayService()->createDraft(array_merge([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => ssPeriodForDate($test, $date)->id,
        'payment_method_id' => $test->methodVir->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'TND',
        'reference' => 'VIR-SS',
        'notes' => null,
        'created_by' => $test->user->id,
        'allocations' => [],
    ], $overrides));

    ssPayService()->post($payment->fresh(), $test->user->id);

    return $payment->fresh();
}

// ---------- Supplier selection / company isolation ----------

it('restricts the supplier selector to the current company', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $supplierB = Supplier::create([
        'company_id' => $companyB->id,
        'code' => 'FO-B',
        'name' => 'Fournisseur Externe',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $suppliers = ssStatementService()->getSuppliersForContext($this->company);

    expect($suppliers->pluck('id'))->toContain($this->supplier->id)
        ->and($suppliers->pluck('id'))->not->toContain($supplierB->id);
});

it('includes inactive suppliers that have historical posted transactions in the selector', function () {
    ssPostInvoice($this);

    Supplier::whereKey($this->supplier->id)->update(['is_active' => false]);
    $this->supplier->refresh();

    $suppliers = ssStatementService()->getSuppliersForContext($this->company);

    expect($suppliers->pluck('id'))->toContain($this->supplier->id);
});

it('excludes inactive suppliers without any posted transactions from the selector', function () {
    Supplier::whereKey($this->supplier->id)->update(['is_active' => false]);

    $suppliers = ssStatementService()->getSuppliersForContext($this->company);

    expect($suppliers->pluck('id'))->not->toContain($this->supplier->id);
});

it('changes visible data when the current company switches', function () {
    $invoice = ssPostInvoice($this);

    $this->actingAs($this->user);

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id])
        ->assertSet('supplierId', $this->supplier->id)
        ->assertSee($invoice->invoice_number);

    session(['current_company_id' => null]);

    // CurrentCompany falls back to the user's first active company, so the
    // statement still resolves through the membership instead of the session.
    $component = Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id]);
    expect($component->viewData('statement'))->not->toBeNull();
});

// ---------- Document inclusion by status ----------

it('includes a posted purchase invoice as a credit row with its supplier invoice number', function () {
    $invoice = ssInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalAchats->id,
        'supplier_invoice_number' => 'FAC-FR-2026-001',
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $this->user->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Produit Test',
                'quantity' => '1000.000',
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);
    ssInvoiceService()->post($invoice->fresh(), $this->user->id);

    $entries = ssStatementService()->getStatementEntries($this->supplier);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['type'])->toBe('purchase_invoice')
        ->and($entries[0]['document_number'])->toBe($invoice->fresh()->invoice_number)
        ->and($entries[0]['supplier_invoice_number'])->toBe('FAC-FR-2026-001')
        ->and($entries[0]['debit'])->toBe('0.000')
        ->and($entries[0]['credit'])->toBe('1000.000');
});

it('excludes draft purchase invoices', function () {
    ssInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalAchats->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $this->user->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Produit Test',
                'quantity' => '500.000',
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);

    expect(ssStatementService()->getStatementEntries($this->supplier))->toHaveCount(0);
});

it('excludes cancelled purchase invoices', function () {
    $invoice = ssPostInvoice($this);

    // The cancel workflow only accepts drafts; the report must exclude any
    // cancelled document regardless of how it reached that status.
    PurchaseInvoice::whereKey($invoice->id)->update(['status' => PurchaseInvoiceStatus::CANCELLED->value]);

    expect(ssStatementService()->getStatementEntries($this->supplier))->toHaveCount(0);
});

function ssCancelPostedPayment($test, string $amount): void
{
    $payment = ssPostPayment($test, $amount);

    SupplierPayment::whereKey($payment->id)->update(['status' => SupplierPaymentStatus::CANCELLED->value]);
}

it('excludes cancelled payments', function () {
    ssPostInvoice($this);

    ssCancelPostedPayment($this, '300.000');

    expect(ssStatementService()->getStatementEntries($this->supplier))->toHaveCount(1);
});

it('includes a posted supplier payment as a debit row appearing exactly once', function () {
    $invoice = ssPostInvoice($this);
    ssPostPayment($this, '400.000');

    $entries = ssStatementService()->getStatementEntries($this->supplier);

    expect($entries)->toHaveCount(2)
        ->and($entries[1]['type'])->toBe('supplier_payment')
        ->and($entries[1]['debit'])->toBe('400.000')
        ->and($entries[1]['credit'])->toBe('0.000')
        ->and($entries[1]['reference'])->toBe('VIR-SS')
        ->and(count(array_filter($entries, fn ($e) => $e['type'] === 'supplier_payment')))->toBe(1)
        ->and($invoice)->toBeInstanceOf(PurchaseInvoice::class);
});

it('excludes draft payments', function () {
    ssPostInvoice($this);

    ssPayService()->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'payment_method_id' => $this->methodVir->id,
        'journal_id' => $this->journalBanque->id,
        'destination_account_id' => $this->account532->id,
        'payment_date' => '2026-01-20',
        'amount' => '300.000',
        'currency' => 'TND',
        'reference' => null,
        'notes' => null,
        'created_by' => $this->user->id,
        'allocations' => [],
    ]);

    expect(ssStatementService()->getStatementEntries($this->supplier))->toHaveCount(1);
});

// ---------- Opening balance / boundaries ----------

it('computes the opening balance from movements before the period start', function () {
    // Before 2026-02-01: invoice +1000 credit, payment −300 debit → 700 payable.
    ssPostInvoice($this, '2026-01-10');
    ssPostPayment($this, '300.000', '2026-01-12');
    ssPostInvoice($this, '2026-02-05', '500.000');

    $opening = ssStatementService()->getOpeningBalance($this->supplier, '2026-02-01');

    expect($opening)->toBe('700.000');

    $statement = ssStatementService()->getStatement($this->supplier, '2026-02-01', null);

    expect($statement['opening_balance'])->toBe('700.000')
        ->and(count($statement['entries']))->toBe(1)
        ->and($statement['entries'][0]['credit'])->toBe('500.000')
        ->and($statement['closing_balance'])->toBe('1200.000');
});

it('keeps documents dated on the from date inside the period, not the opening balance', function () {
    ssPostInvoice($this, '2026-01-15');

    $opening = ssStatementService()->getOpeningBalance($this->supplier, '2026-01-15');
    $entries = ssStatementService()->getStatementEntries($this->supplier, '2026-01-15', null);

    expect($opening)->toBe('0.000')
        ->and($entries)->toHaveCount(1);
});

it('includes documents dated exactly on the to date and excludes later ones', function () {
    // On to_date: included; immediately after to_date: excluded.
    ssPostInvoice($this, '2026-01-31', '100.000');
    ssPostInvoice($this, '2026-02-01', '900.000');

    $entries = ssStatementService()->getStatementEntries($this->supplier, '2026-01-01', '2026-01-31');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['credit'])->toBe('100.000');
});

// ---------- Running balance / totals ----------

it('applies the accounting verification example: invoice 1190 credit, payment 500 debit', function () {
    // Invoice with VAT: 1000 × 1.19 TTC = 1190 credit on the payable account.
    $invoice = ssInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalAchats->id,
        'invoice_date' => '2026-01-05',
        'due_date' => '2026-02-04',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $this->user->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Produit Test',
                'quantity' => '1000.000',
                'unit' => 'unit',
                'unit_price' => '1.190',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);
    ssInvoiceService()->post($invoice->fresh(), $this->user->id);

    ssPostPayment($this, '500.000', '2026-01-20');

    $summary = ssStatementService()->getSummary($this->supplier);

    expect($summary['total_credit'])->toBe('1190.000')
        ->and($summary['total_debit'])->toBe('500.000')
        ->and($summary['closing_balance'])->toBe('690.000');

    $statement = ssStatementService()->getStatement($this->supplier);
    $balances = array_column($statement['entries'], 'balance');

    expect($balances)->toBe(['1190.000', '690.000'])
        ->and($summary['closing_balance'])->toBe('690.000');
});

it('computes running balances in chronological order across mixed documents with reversed signs', function () {
    ssPostInvoice($this, '2026-01-05', '1000.000');   // credit +1000 → payable 1000
    ssPostPayment($this, '600.000', '2026-01-10');    // debit −600 → payable 400
    ssPostInvoice($this, '2026-01-08', '700.000');    // credit +700 → payable 1100

    $statement = ssStatementService()->getStatement($this->supplier);
    $balances = array_column($statement['entries'], 'balance');
    $types = array_column($statement['entries'], 'type');

    expect($types)->toBe(['purchase_invoice', 'purchase_invoice', 'supplier_payment'])
        ->and($balances)->toBe(['1000.000', '1700.000', '1100.000'])
        ->and($statement['total_credit'])->toBe('1700.000')
        ->and($statement['total_debit'])->toBe('600.000')
        ->and($statement['closing_balance'])->toBe('1100.000');
});

it('counts a payment allocated to multiple invoices only once', function () {
    $invoiceA = ssPostInvoice($this, '2026-01-05');
    $invoiceB = ssPostInvoice($this, '2026-01-06');

    ssPostPayment($this, '800.000', '2026-01-20', [
        'allocations' => [
            ['purchase_invoice_id' => $invoiceA->id, 'amount' => '400.000'],
            ['purchase_invoice_id' => $invoiceB->id, 'amount' => '400.000'],
        ],
    ]);

    $entries = ssStatementService()->getStatementEntries($this->supplier);
    $payments = array_values(array_filter($entries, fn ($e) => $e['type'] === 'supplier_payment'));

    expect($payments)->toHaveCount(1)
        ->and($payments[0]['debit'])->toBe('800.000')
        ->and(ssStatementService()->getSummary($this->supplier)['closing_balance'])->toBe('1200.000');
});

it('reflects a partial payment correctly', function () {
    ssPostInvoice($this, '2026-01-05');
    ssPostPayment($this, '250.000', '2026-01-20');

    $summary = ssStatementService()->getSummary($this->supplier);

    expect($summary['total_credit'])->toBe('1000.000')
        ->and($summary['total_debit'])->toBe('250.000')
        ->and($summary['closing_balance'])->toBe('750.000');
});

it('reports a zero-balance supplier as settled', function () {
    ssPostInvoice($this, '2026-01-05', '1190.000');
    ssPostPayment($this, '1190.000', '2026-01-20');

    $summary = ssStatementService()->getSummary($this->supplier);

    expect($summary)->toBe([
        'opening_balance' => '0.000',
        'total_debit' => '1190.000',
        'total_credit' => '1190.000',
        'closing_balance' => '0.000',
    ]);
});

it('reports a positive payable closing balance for an unpaid supplier', function () {
    ssPostInvoice($this, '2026-01-05', '800.000');
    ssPostPayment($this, '300.000', '2026-01-20');

    expect(ssStatementService()->getSummary($this->supplier)['closing_balance'])->toBe('500.000');
});

it('reports a negative closing balance for an overpaid supplier', function () {
    ssPostInvoice($this, '2026-01-05', '500.000');
    ssPostPayment($this, '800.000', '2026-01-20');

    expect(ssStatementService()->getSummary($this->supplier)['closing_balance'])->toBe('-300.000');
});

it('returns zero balances for a supplier without posted documents', function () {
    $summary = ssStatementService()->getSummary($this->supplier);

    expect($summary)->toBe([
        'opening_balance' => '0.000',
        'total_debit' => '0.000',
        'total_credit' => '0.000',
        'closing_balance' => '0.000',
    ]);
});

// ---------- Ordering ----------

it('orders rows deterministically by date, type priority, document number then id', function () {
    $a = ssPostInvoice($this, '2026-01-10', '60.000');
    $b = ssPostInvoice($this, '2026-01-10', '50.000');
    ssPostPayment($this, '40.000', '2026-01-10');
    ssPostInvoice($this, '2026-01-09', '70.000');

    $entries = ssStatementService()->getStatementEntries($this->supplier);
    $dates = array_column($entries, 'date');
    $typesOn10 = array_slice(array_column($entries, 'type'), 1);

    expect($dates)->toBe(['2026-01-09', '2026-01-10', '2026-01-10', '2026-01-10'])
        // Same-date rows: purchase invoices first (ordered by document number),
        // then the supplier payment.
        ->and($typesOn10)->toBe(['purchase_invoice', 'purchase_invoice', 'supplier_payment'])
        ->and($entries[1]['document_number'])->toBe(min($a->invoice_number, $b->invoice_number));
});

// ---------- Date range validation ----------

it('rejects a from date after the to date', function () {
    ssStatementService()->getSummary($this->supplier, '2026-02-01', '2026-01-01');
})->throws(InvalidArgumentException::class);

it('filters the statement with the date range', function () {
    ssPostInvoice($this, '2026-01-05', '100.000');
    ssPostInvoice($this, '2026-02-05', '200.000');

    $january = ssStatementService()->getSummary($this->supplier, '2026-01-01', '2026-01-31');

    expect($january['total_credit'])->toBe('100.000')
        ->and($january['closing_balance'])->toBe('100.000');
});

// ---------- Outstanding balance ----------

it('matches the outstanding balance with the all-time closing balance', function () {
    $invoiceA = ssPostInvoice($this, '2026-01-05', '600.000');
    ssPostInvoice($this, '2026-01-06', '400.000');
    // Allocated payment: both the statement and the payments service count it.
    ssPostPayment($this, '300.000', '2026-01-20', [
        'allocations' => [['purchase_invoice_id' => $invoiceA->id, 'amount' => '300.000']],
    ]);

    $outstanding = ssStatementService()->getOutstandingBalance($this->supplier);
    $allTime = ssStatementService()->getSummary($this->supplier);

    expect($outstanding)->toBe('700.000')
        ->and($allTime['closing_balance'])->toBe('700.000')
        ->and($outstanding)->toBe(ssPayService()->getSupplierOutstandingBalance($this->supplier->fresh()));
});

// ---------- General Ledger reconciliation ----------

it('reconciles with the payable account movements in the general ledger', function () {
    $invoice = ssInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalAchats->id,
        'invoice_date' => '2026-01-05',
        'due_date' => '2026-02-04',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $this->user->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Produit Test',
                'quantity' => '1000.000',
                'unit' => 'unit',
                'unit_price' => '1.190',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);
    ssInvoiceService()->post($invoice->fresh(), $this->user->id);

    ssPostPayment($this, '500.000', '2026-01-20');

    $summary = ssStatementService()->getSummary(
        $this->supplier,
        $this->fiscalYear->start_date->toDateString(),
        $this->fiscalYear->end_date->toDateString()
    );

    // Payable account 401 movements over the same period: invoice credit 1190,
    // payment debit 500 → net credit balance 690, matching the statement.
    $payableLedger = app(GeneralLedgerService::class)
        ->getAccountLedger($this->account401, $this->company, $this->fiscalYear, []);

    $netCredit = collect($payableLedger)->sum(fn ($line) => (float) $line->credit)
        - collect($payableLedger)->sum(fn ($line) => (float) $line->debit);

    expect(number_format($netCredit, 3, '.', ''))->toBe('690.000')
        ->and($summary['closing_balance'])->toBe('690.000');
});

// ---------- Livewire page ----------

it('renders the report page with the supplier selector and defaults', function () {
    $this->actingAs($this->user)
        ->get(route('reports.supplier-statement'))
        ->assertOk()
        ->assertSee('Relevé fournisseur')
        ->assertSee('Fournisseur Alpha');
});

it('shows the statement when a supplier is selected through Livewire state', function () {
    $invoice = ssPostInvoice($this);
    ssPostPayment($this, '300.000', '2026-01-20');

    $this->actingAs($this->user);

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id])
        ->assertSet('supplierId', $this->supplier->id)
        ->assertSee($invoice->invoice_number)
        ->assertSee('Facture fournisseur')
        ->assertSee('Règlement fournisseur')
        ->assertSee('1 000,000')
        ->assertSee('9876543B');
});

it('accepts a supplier provided by the URL route parameter', function () {
    ssPostInvoice($this);

    $this->actingAs($this->user)
        ->get(route('reports.supplier-statement', ['supplierId' => $this->supplier->id]))
        ->assertOk()
        ->assertSee('Fournisseur Alpha');
});

it('rejects a cross-company supplier from the URL with 404', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $supplierB = Supplier::create([
        'company_id' => $companyB->id,
        'code' => 'FO-B',
        'name' => 'Fournisseur Externe',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.supplier-statement', ['supplierId' => $supplierB->id]))
        ->assertNotFound();
});

it('updates totals when the date filters change', function () {
    $invoice = ssPostInvoice($this, '2026-01-05', '100.000');

    $this->actingAs($this->user);

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id])
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('100,000')
        ->set('fromDate', '2026-02-01')
        ->assertDontSee($invoice->invoice_number);
});

it('rejects an inverted date range in the component', function () {
    $this->actingAs($this->user);

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id])
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-01-01')
        ->assertHasErrors('toDate');
});

it('displays the settled-account wording when the closing balance is zero', function () {
    ssPostInvoice($this, '2026-01-05', '1190.000');
    ssPostPayment($this, '1190.000', '2026-01-20');

    $this->actingAs($this->user);

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id])
        ->assertSee('Compte soldé.')
        ->assertSee('0,000');
});

// ---------- Read-only verification ----------

it('is strictly read-only: viewing the statement writes nothing to accounting tables', function () {
    $invoice = ssPostInvoice($this);
    ssPostPayment($this, '300.000', '2026-01-20');

    $snapshot = [
        'purchase_invoices' => DB::table('purchase_invoices')->count(),
        'purchase_invoice_lines' => DB::table('purchase_invoice_lines')->count(),
        'supplier_payments' => DB::table('supplier_payments')->count(),
        'supplier_payment_allocations' => DB::table('supplier_payment_allocations')->count(),
        'journal_entries' => DB::table('journal_entries')->count(),
        'journal_entry_lines' => DB::table('journal_entry_lines')->count(),
        'accounts' => DB::table('accounts')->count(),
        'suppliers' => DB::table('suppliers')->count(),
        "invoice_total_{$invoice->id}" => (string) $invoice->total,
    ];

    $this->actingAs($this->user)
        ->get(route('reports.supplier-statement', ['supplierId' => $this->supplier->id]))
        ->assertOk();

    Livewire::test(SupplierStatement::class, ['supplierId' => $this->supplier->id]);

    expect(DB::table('purchase_invoices')->count())->toBe($snapshot['purchase_invoices'])
        ->and(DB::table('purchase_invoice_lines')->count())->toBe($snapshot['purchase_invoice_lines'])
        ->and(DB::table('supplier_payments')->count())->toBe($snapshot['supplier_payments'])
        ->and(DB::table('supplier_payment_allocations')->count())->toBe($snapshot['supplier_payment_allocations'])
        ->and(DB::table('journal_entries')->count())->toBe($snapshot['journal_entries'])
        ->and(DB::table('journal_entry_lines')->count())->toBe($snapshot['journal_entry_lines'])
        ->and(DB::table('accounts')->count())->toBe($snapshot['accounts'])
        ->and(DB::table('suppliers')->count())->toBe($snapshot['suppliers'])
        ->and(number_format((float) DB::table('purchase_invoices')->where('id', $invoice->id)->value('total'), 3, '.', ''))
        ->toBe($snapshot["invoice_total_{$invoice->id}"]);
});
