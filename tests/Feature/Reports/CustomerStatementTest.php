<?php

use App\Enums\CreditNoteStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Livewire\Reports\CustomerStatement;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\CustomerPaymentPostingService;
use App\Services\Accounting\CustomerStatementService;
use App\Services\Accounting\JournalEntryService;
use App\Services\CreditNoteService;
use App\Services\CustomerPaymentService;
use App\Services\InvoiceService;
use App\Services\SalesCreditNotePostingService;
use App\Services\SalesInvoicePostingService;
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

    $this->journalVentes = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'VTE',
        'name' => 'Ventes',
        'type' => 'ventes',
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

    $this->account411 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '411000',
        'name' => 'Clients',
        'account_type' => 'asset',
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

    $this->account707 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '707000',
        'name' => 'Ventes de marchandises',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $this->account4457 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445700',
        'name' => 'TVA collectée',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Alpha',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'tax_identifier' => '1234567A',
        'account_id' => $this->account411->id,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'sales_tax_account_id' => $this->account4457->id,
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
        'sale_price' => '25.000',
        'sales_account_id' => $this->account707->id,
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
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

function csStatementService(): CustomerStatementService
{
    return new CustomerStatementService;
}

function csInvoiceService(): InvoiceService
{
    return new InvoiceService(new SalesInvoicePostingService(new JournalEntryService));
}

function csCnService(): CreditNoteService
{
    return new CreditNoteService(new SalesCreditNotePostingService(new JournalEntryService));
}

function csPayService(): CustomerPaymentService
{
    return new CustomerPaymentService(new CustomerPaymentPostingService(
        new JournalEntryService
    ));
}

function csPeriodForDate($test, string $date): AccountingPeriod
{
    $month = (int) substr($date, 5, 2);

    return $test->periods[$month];
}

/**
 * Posted invoice of round total: quantity × 1.000, no tax.
 */
function csPostInvoice($test, string $date = '2026-01-15', string $quantity = '1000.000'): Invoice
{
    $invoice = csInvoiceService()->createDraft([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => csPeriodForDate($test, $date)->id,
        'journal_id' => $test->journalVentes->id,
        'invoice_date' => $date,
        'due_date' => $date,
        'currency' => 'TND',
        'payment_terms_days' => 0,
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

    csInvoiceService()->post($invoice->fresh(), $test->user->id);

    return $invoice->fresh();
}

function csPostCreditNote($test, Invoice $invoice, string $quantity, string $date = '2026-01-18'): CreditNote
{
    $lines = [];

    foreach ($invoice->lines()->orderBy('sort_order')->get() as $line) {
        $lines[] = [
            'invoice_line_id' => $line->id,
            'product_id' => $line->product_id,
            'description' => (string) $line->description,
            'quantity' => $quantity,
            'unit' => $line->unit,
            'unit_price' => (string) $line->unit_price,
            'discount_percent' => (string) $line->discount_percent,
            'tax_rate_id' => $line->tax_rate_id,
            'tax_code' => $line->tax_code,
            'tax_rate_value' => (string) ($line->tax_rate ?? '0'),
            'sales_account_id' => $line->sales_account_id,
        ];
    }

    $cn = csCnService()->createDraft([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => csPeriodForDate($test, $date)->id,
        'journal_id' => $test->journalVentes->id,
        'invoice_id' => $invoice->id,
        'credit_note_date' => $date,
        'reason' => 'Retour marchandise',
        'currency' => 'TND',
        'notes' => null,
        'created_by' => $test->user->id,
        'lines' => $lines,
    ]);

    csCnService()->post($cn->fresh(), $test->user->id);

    return $cn->fresh();
}

function csPostPayment($test, string $amount, string $date = '2026-01-20', array $overrides = []): CustomerPayment
{
    $payment = csPayService()->createDraft(array_merge([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => csPeriodForDate($test, $date)->id,
        'payment_method_id' => $test->methodVir->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'TND',
        'reference' => 'VIR-CS',
        'notes' => null,
        'created_by' => $test->user->id,
        'allocations' => [],
    ], $overrides));

    csPayService()->post($payment->fresh(), $test->user->id);

    return $payment->fresh();
}

// ---------- Customer selection / company isolation ----------

it('restricts the customer selector to the current company', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $customerB = Customer::create([
        'company_id' => $companyB->id,
        'code' => 'CL-B',
        'name' => 'Client Externe',
        'customer_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $customers = csStatementService()->getCustomersForContext($this->company);

    expect($customers->pluck('id'))->toContain($this->customer->id)
        ->and($customers->pluck('id'))->not->toContain($customerB->id);
});

it('includes inactive customers that have historical posted transactions in the selector', function () {
    csPostInvoice($this);

    Customer::whereKey($this->customer->id)->update(['is_active' => false]);
    $this->customer->refresh();

    $customers = csStatementService()->getCustomersForContext($this->company);

    expect($customers->pluck('id'))->toContain($this->customer->id);
});

it('excludes inactive customers without any posted transactions from the selector', function () {
    Customer::whereKey($this->customer->id)->update(['is_active' => false]);

    $customers = csStatementService()->getCustomersForContext($this->company);

    expect($customers->pluck('id'))->not->toContain($this->customer->id);
});

it('changes visible data when the current company switches', function () {
    $invoice = csPostInvoice($this);

    $this->actingAs($this->user);

    Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id])
        ->assertSet('customerId', $this->customer->id)
        ->assertSee($invoice->invoice_number);

    session(['current_company_id' => null]);

    // CurrentCompany falls back to the user's first active company, so the
    // statement still resolves through the membership instead of the session.
    $component = Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id]);
    expect($component->viewData('statement'))->not->toBeNull();
});

// ---------- Document inclusion by status ----------

it('includes a posted invoice as a debit row', function () {
    $invoice = csPostInvoice($this);

    $entries = csStatementService()->getStatementEntries($this->customer);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['type'])->toBe('invoice')
        ->and($entries[0]['document_number'])->toBe($invoice->invoice_number)
        ->and($entries[0]['debit'])->toBe('1000.000')
        ->and($entries[0]['credit'])->toBe('0.000');
});

it('excludes draft invoices', function () {
    csInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalVentes->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-01-15',
        'currency' => 'TND',
        'payment_terms_days' => 0,
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

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(0);
});

it('excludes cancelled invoices', function () {
    $invoice = csPostInvoice($this);

    // The cancel workflow only accepts drafts; the report must exclude any
    // cancelled document regardless of how it reached that status.
    Invoice::whereKey($invoice->id)->update(['status' => InvoiceStatus::CANCELLED->value]);

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(0);
});

it('includes a posted credit note as a credit row', function () {
    $invoice = csPostInvoice($this);
    csPostCreditNote($this, $invoice, '190.000');

    $entries = csStatementService()->getStatementEntries($this->customer);

    expect($entries)->toHaveCount(2)
        ->and($entries[1]['type'])->toBe('credit_note')
        ->and($entries[1]['debit'])->toBe('0.000')
        ->and($entries[1]['credit'])->toBe('190.000')
        ->and($entries[1]['reference'])->toBe('Retour marchandise');
});

it('excludes draft credit notes', function () {
    $invoice = csPostInvoice($this);

    $lines = [];
    foreach ($invoice->lines()->orderBy('sort_order')->get() as $line) {
        $lines[] = [
            'invoice_line_id' => $line->id,
            'product_id' => $line->product_id,
            'description' => (string) $line->description,
            'quantity' => '100.000',
            'unit' => $line->unit,
            'unit_price' => (string) $line->unit_price,
            'discount_percent' => (string) $line->discount_percent,
            'tax_rate_id' => $line->tax_rate_id,
            'tax_code' => $line->tax_code,
            'tax_rate_value' => (string) ($line->tax_rate ?? '0'),
            'sales_account_id' => $line->sales_account_id,
        ];
    }

    csCnService()->createDraft([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalVentes->id,
        'invoice_id' => $invoice->id,
        'credit_note_date' => '2026-01-18',
        'reason' => 'Retour brouillon',
        'currency' => 'TND',
        'notes' => null,
        'created_by' => $this->user->id,
        'lines' => $lines,
    ]);

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(1);
});

function csCancelPostedCreditNote($test, Invoice $invoice, string $quantity): void
{
    $creditNote = csPostCreditNote($test, $invoice, $quantity);

    CreditNote::whereKey($creditNote->id)->update(['status' => CreditNoteStatus::CANCELLED->value]);
}

it('excludes cancelled credit notes', function () {
    $invoice = csPostInvoice($this);

    csCancelPostedCreditNote($this, $invoice, '100.000');

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(1);
});

function csCancelPostedPayment($test, string $amount): void
{
    $payment = csPostPayment($test, $amount);

    CustomerPayment::whereKey($payment->id)->update(['status' => CustomerPaymentStatus::CANCELLED->value]);
}

it('includes a posted payment as a credit row appearing exactly once', function () {
    $invoice = csPostInvoice($this);
    csPostPayment($this, '400.000');

    $entries = csStatementService()->getStatementEntries($this->customer);

    expect($entries)->toHaveCount(2)
        ->and($entries[1]['type'])->toBe('payment')
        ->and($entries[1]['debit'])->toBe('0.000')
        ->and($entries[1]['credit'])->toBe('400.000')
        ->and(count(array_filter($entries, fn ($e) => $e['type'] === 'payment')))->toBe(1)
        ->and($invoice)->toBeInstanceOf(Invoice::class);
});

it('excludes draft payments', function () {
    csPostInvoice($this);

    csPayService()->createDraft([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
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

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(1);
});

it('excludes cancelled payments', function () {
    csPostInvoice($this);

    csCancelPostedPayment($this, '300.000');

    expect(csStatementService()->getStatementEntries($this->customer))->toHaveCount(1);
});

// ---------- Opening balance / boundaries ----------

it('computes the opening balance from movements before the period start', function () {
    csPostInvoice($this, '2026-01-10');
    csPostPayment($this, '300.000', '2026-01-12');
    csPostInvoice($this, '2026-02-05', '500.000');

    $opening = csStatementService()->getOpeningBalance($this->customer, '2026-02-01');

    expect($opening)->toBe('700.000');

    $statement = csStatementService()->getStatement($this->customer, '2026-02-01', null);

    expect($statement['opening_balance'])->toBe('700.000')
        ->and(count($statement['entries']))->toBe(1)
        ->and($statement['entries'][0]['debit'])->toBe('500.000')
        ->and($statement['closing_balance'])->toBe('1200.000');
});

it('keeps documents dated on the from date inside the period, not the opening balance', function () {
    csPostInvoice($this, '2026-01-15');

    $opening = csStatementService()->getOpeningBalance($this->customer, '2026-01-15');
    $entries = csStatementService()->getStatementEntries($this->customer, '2026-01-15', null);

    expect($opening)->toBe('0.000')
        ->and($entries)->toHaveCount(1);
});

it('includes documents dated exactly on the to date and excludes later ones', function () {
    csPostInvoice($this, '2026-01-31', '100.000');
    csPostInvoice($this, '2026-02-01', '900.000');

    $entries = csStatementService()->getStatementEntries($this->customer, '2026-01-01', '2026-01-31');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['debit'])->toBe('100.000');
});

// ---------- Running balance / totals ----------

it('applies the accounting verification example: invoice 1190, credit note 190, payment 500', function () {
    // Invoice with VAT: 1000 × 1.19 TTC = 1190.
    $invoice = csInvoiceService()->createDraft([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->periods[1]->id,
        'journal_id' => $this->journalVentes->id,
        'invoice_date' => '2026-01-05',
        'due_date' => '2026-01-05',
        'currency' => 'TND',
        'payment_terms_days' => 0,
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
    csInvoiceService()->post($invoice->fresh(), $this->user->id);

    csPostCreditNote($this, $invoice->fresh(), '159.664'); // ≈ 190 TTC (159.664 × 1.19)
    csPostPayment($this, '500.000', '2026-01-20');

    $summary = csStatementService()->getSummary($this->customer);

    expect($summary['total_debit'])->toBe('1190.000')
        ->and($summary['closing_balance'])->toBe('500.000');

    $statement = csStatementService()->getStatement($this->customer);
    $balances = array_column($statement['entries'], 'balance');

    expect($balances)->toBe(['1190.000', '1000.000', '500.000'])
        ->and($statement['total_credit'])->toBe('690.000')
        ->and($statement['closing_balance'])->toBe('500.000');
});

it('computes running balances in chronological order across mixed documents', function () {
    csPostInvoice($this, '2026-01-05', '1000.000');   // +1000
    csPostPayment($this, '600.000', '2026-01-10');    // −600 → 400
    csPostCreditNote($this, csPostInvoice($this, '2026-01-08'), '100.000', '2026-01-12'); // second invoice +1000, CN −100

    $statement = csStatementService()->getStatement($this->customer);
    $balances = array_column($statement['entries'], 'balance');

    expect($balances)->toBe(['1000.000', '2000.000', '1400.000', '1300.000'])
        ->and($statement['total_debit'])->toBe('2000.000')
        ->and($statement['total_credit'])->toBe('700.000')
        ->and($statement['closing_balance'])->toBe('1300.000');
});

it('counts a payment allocated to multiple invoices only once', function () {
    $invoiceA = csPostInvoice($this, '2026-01-05');
    $invoiceB = csPostInvoice($this, '2026-01-06');

    csPostPayment($this, '800.000', '2026-01-20', [
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'amount' => '400.000'],
            ['invoice_id' => $invoiceB->id, 'amount' => '400.000'],
        ],
    ]);

    $entries = csStatementService()->getStatementEntries($this->customer);
    $payments = array_values(array_filter($entries, fn ($e) => $e['type'] === 'payment'));

    expect($payments)->toHaveCount(1)
        ->and($payments[0]['credit'])->toBe('800.000')
        ->and(csStatementService()->getSummary($this->customer)['closing_balance'])->toBe('1200.000');
});

it('reflects a partial payment correctly', function () {
    csPostInvoice($this, '2026-01-05');
    csPostPayment($this, '250.000', '2026-01-20');

    $summary = csStatementService()->getSummary($this->customer);

    expect($summary['total_debit'])->toBe('1000.000')
        ->and($summary['total_credit'])->toBe('250.000')
        ->and($summary['closing_balance'])->toBe('750.000');
});

it('reports a zero-balance customer as settled', function () {
    csPostInvoice($this, '2026-01-05');
    csPostPayment($this, '1000.000', '2026-01-20');

    $summary = csStatementService()->getSummary($this->customer);

    expect($summary['closing_balance'])->toBe('0.000');
});

it('reports a negative closing balance for an overpaid customer', function () {
    csPostInvoice($this, '2026-01-05', '500.000');
    csPostPayment($this, '800.000', '2026-01-20');

    $summary = csStatementService()->getSummary($this->customer);

    expect($summary['closing_balance'])->toBe('-300.000');
});

it('returns zero balances for a customer without posted documents', function () {
    $summary = csStatementService()->getSummary($this->customer);

    expect($summary)->toBe([
        'opening_balance' => '0.000',
        'total_debit' => '0.000',
        'total_credit' => '0.000',
        'closing_balance' => '0.000',
    ]);
});

// ---------- Ordering ----------

it('orders rows deterministically by date, type priority, document number then id', function () {
    $a = csPostInvoice($this, '2026-01-10');
    csPostCreditNote($this, $a, '50.000', '2026-01-10');
    csPostPayment($this, '60.000', '2026-01-10');
    csPostInvoice($this, '2026-01-09', '70.000');

    $entries = csStatementService()->getStatementEntries($this->customer);
    $types = array_column($entries, 'type');
    $dates = array_column($entries, 'date');

    expect($dates)->toBe(['2026-01-09', '2026-01-10', '2026-01-10', '2026-01-10'])
        ->and(array_slice($types, 1))->toBe(['invoice', 'credit_note', 'payment']);
});

// ---------- Date range validation ----------

it('rejects a from date after the to date', function () {
    csStatementService()->getSummary($this->customer, '2026-02-01', '2026-01-01');
})->throws(InvalidArgumentException::class);

it('filters the statement with the date range', function () {
    csPostInvoice($this, '2026-01-05', '100.000');
    csPostInvoice($this, '2026-02-05', '200.000');

    $january = csStatementService()->getSummary($this->customer, '2026-01-01', '2026-01-31');

    expect($january['total_debit'])->toBe('100.000')
        ->and($january['closing_balance'])->toBe('100.000');
});

// ---------- Outstanding balance ----------

it('matches the outstanding balance with the all-time closing balance', function () {
    csPostInvoice($this, '2026-01-05');
    csPostPayment($this, '300.000', '2026-01-20');
    $invoice = Invoice::where('customer_id', $this->customer->id)->first();
    csPostCreditNote($this, $invoice, '100.000');

    $outstanding = csStatementService()->getOutstandingBalance($this->customer);
    $allTime = csStatementService()->getSummary($this->customer);

    expect($outstanding)->toBe('600.000')
        ->and($allTime['closing_balance'])->toBe('600.000');
});

// ---------- Livewire page ----------

it('renders the report page with the customer selector and defaults', function () {
    $this->actingAs($this->user)
        ->get(route('reports.customer-statement'))
        ->assertOk()
        ->assertSee('Relevé client')
        ->assertSee('Client Alpha');
});

it('shows the statement when a customer is selected through Livewire state', function () {
    $invoice = csPostInvoice($this);

    $this->actingAs($this->user);

    Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id])
        ->assertSet('customerId', $this->customer->id)
        ->assertSee($invoice->invoice_number)
        ->assertSee('Facture')
        ->assertSee('1 000,000');
});

it('accepts a customer provided by the URL route parameter', function () {
    csPostInvoice($this);

    $this->actingAs($this->user)
        ->get(route('reports.customer-statement', ['customerId' => $this->customer->id]))
        ->assertOk()
        ->assertSee('Client Alpha');
});

it('rejects a cross-company customer from the URL with 404', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $customerB = Customer::create([
        'company_id' => $companyB->id,
        'code' => 'CL-B',
        'name' => 'Client Externe',
        'customer_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.customer-statement', ['customerId' => $customerB->id]))
        ->assertNotFound();
});

it('updates totals when the date filters change', function () {
    $invoice = csPostInvoice($this, '2026-01-05', '100.000');

    $this->actingAs($this->user);

    Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id])
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('100,000')
        ->set('fromDate', '2026-02-01')
        ->assertDontSee($invoice->invoice_number);
});

it('rejects an inverted date range in the component', function () {
    $this->actingAs($this->user);

    Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id])
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-01-01')
        ->assertHasErrors('toDate');
});

it('is strictly read-only: viewing the statement writes nothing to accounting tables', function () {
    $invoice = csPostInvoice($this);
    csPostPayment($this, '300.000', '2026-01-20');

    $snapshot = [
        'invoices' => DB::table('invoices')->count(),
        'invoice_lines' => DB::table('invoice_lines')->count(),
        'credit_notes' => DB::table('credit_notes')->count(),
        'customer_payments' => DB::table('customer_payments')->count(),
        'customer_payment_allocations' => DB::table('customer_payment_allocations')->count(),
        'journal_entries' => DB::table('journal_entries')->count(),
        'journal_entry_lines' => DB::table('journal_entry_lines')->count(),
        'accounts' => DB::table('accounts')->count(),
        'customers' => DB::table('customers')->count(),
    ];

    $this->actingAs($this->user)
        ->get(route('reports.customer-statement', ['customerId' => $this->customer->id]))
        ->assertOk();

    Livewire::test(CustomerStatement::class, ['customerId' => $this->customer->id]);

    expect(DB::table('invoices')->count())->toBe($snapshot['invoices'])
        ->and(DB::table('invoice_lines')->count())->toBe($snapshot['invoice_lines'])
        ->and(DB::table('credit_notes')->count())->toBe($snapshot['credit_notes'])
        ->and(DB::table('customer_payments')->count())->toBe($snapshot['customer_payments'])
        ->and(DB::table('customer_payment_allocations')->count())->toBe($snapshot['customer_payment_allocations'])
        ->and(DB::table('journal_entries')->count())->toBe($snapshot['journal_entries'])
        ->and(DB::table('journal_entry_lines')->count())->toBe($snapshot['journal_entry_lines'])
        ->and(DB::table('accounts')->count())->toBe($snapshot['accounts'])
        ->and(DB::table('customers')->count())->toBe($snapshot['customers']);
});
