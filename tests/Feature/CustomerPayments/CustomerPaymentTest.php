<?php

use App\Enums\CustomerPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\PaymentMethodType;
use App\Livewire\CustomerPayments\Create;
use App\Livewire\CustomerPayments\Edit;
use App\Livewire\CustomerPayments\Index;
use App\Livewire\CustomerPayments\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
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
        'name' => 'Test Company',
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

    $this->period = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'name' => 'Janvier 2026',
        'code' => '2026-01',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);

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

    $this->journalCaisse = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'CSQ',
        'name' => 'Caisses',
        'type' => 'caisse',
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

    $this->account571 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '571000',
        'name' => 'Caisse',
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
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
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

    $this->methodEsp = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'ESP',
        'name' => 'Espèces',
        'type' => PaymentMethodType::CASH,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);
});

// ---------- Helpers ----------

function cpService(): CustomerPaymentService
{
    return new CustomerPaymentService(new CustomerPaymentPostingService(
        new JournalEntryService
    ));
}

function cpCnService(): CreditNoteService
{
    return new CreditNoteService(new SalesCreditNotePostingService(
        new JournalEntryService
    ));
}

/**
 * Simple invoice with round numbers: 1000 units at 1.000, no discount, no tax.
 */
function makeSimpleInvoiceData($test): array
{
    return [
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journalVentes->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $test->user->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => '1000.000',
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ];
}

function createPostedInvoiceForPaymentTest($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    $invoice = $service->createDraft(makeSimpleInvoiceData($test));
    $service->post($invoice->fresh(), $test->user->id);

    return $invoice->fresh();
}

function makePaymentData($test, array $overrides = []): array
{
    return array_merge([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->methodVir->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => '2026-01-20',
        'amount' => '250.000',
        'currency' => 'TND',
        'reference' => 'VIR-001',
        'notes' => null,
        'created_by' => $test->user->id,
        'allocations' => [],
    ], $overrides);
}

function createDraftPaymentForTest($test, array $overrides = []): CustomerPayment
{
    return cpService()->createDraft(makePaymentData($test, $overrides));
}

function makeCpCnLinesData(Invoice $invoice, ?string $quantity = null): array
{
    $lines = [];
    foreach ($invoice->lines()->orderBy('sort_order')->get() as $line) {
        $lines[] = [
            'invoice_line_id' => $line->id,
            'product_id' => $line->product_id,
            'description' => (string) $line->description,
            'quantity' => $quantity ?? (string) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => (string) $line->unit_price,
            'discount_percent' => (string) $line->discount_percent,
            'tax_rate_id' => $line->tax_rate_id,
            'tax_code' => $line->tax_code,
            'tax_rate_value' => (string) ($line->tax_rate ?? '0'),
            'sales_account_id' => $line->sales_account_id,
        ];
    }

    return $lines;
}

// ---------- Numbering ----------

it('generates sequential payment numbers scoped per company', function () {
    $service = cpService();

    expect($service->generatePaymentNumber($this->company->id))->toBe('REG-'.now()->year.'-000001');

    createDraftPaymentForTest($this);

    expect($service->generatePaymentNumber($this->company->id))->toBe('REG-'.now()->year.'-000002');
});

// ---------- createDraft ----------

it('creates a draft payment with allocations and computes allocated and unallocated amounts', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    $payment = createDraftPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '400.000']],
    ]);

    expect($payment->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and($payment->payment_number)->toBe('REG-'.now()->year.'-000001')
        ->and($payment->amount)->toBe('500.000')
        ->and($payment->currency)->toBe('TND')
        ->and($payment->journal_id)->toBe($this->journalBanque->id)
        ->and($payment->destination_account_id)->toBe($this->account532->id)
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocatedAmount())->toBe('400.000')
        ->and($payment->unallocatedAmount())->toBe('100.000');
});

it('auto-resolves the banque journal for bank transfer methods when omitted', function () {
    createPostedInvoiceForPaymentTest($this);

    $data = makePaymentData($this);
    unset($data['journal_id']);

    $payment = cpService()->createDraft($data);

    expect($payment->fresh()->journal_id)->toBe($this->journalBanque->id);
});

it('uses the configured default bank account as destination when omitted', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $data = makePaymentData($this);
    unset($data['destination_account_id']);

    $payment = cpService()->createDraft($data);

    expect($payment->fresh()->destination_account_id)->toBe($this->account532->id);
});

it('refuses an other-type method without explicit journal', function () {
    $other = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'AUT',
        'name' => 'Autre',
        'type' => PaymentMethodType::OTHER,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 9,
    ]);

    $data = makePaymentData($this, ['payment_method_id' => $other->id]);
    unset($data['journal_id']);

    expect(fn () => cpService()->createDraft($data))->toThrow(InvalidArgumentException::class);
});

it('refuses allocations exceeding the invoice remaining balance', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    expect(fn () => createDraftPaymentForTest($this, [
        'amount' => '2000.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '1500.000']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('refuses allocations totalling more than the payment amount', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    expect(fn () => createDraftPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '600.000']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('refuses allocating a non-posted invoice', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    $draftInvoice = $service->createDraft(makeSimpleInvoiceData($this));

    expect(fn () => createDraftPaymentForTest($this, [
        'allocations' => [['invoice_id' => $draftInvoice->id, 'amount' => '100.000']],
    ]))->toThrow(InvalidArgumentException::class)
        ->and(Invoice::find($draftInvoice->id)->status)->not->toBe(InvoiceStatus::POSTED);
});

// ---------- createFromInvoice ----------

it('creates a draft from an invoice preselecting the remaining amount and allocation', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_journal_id' => $this->journalBanque->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);

    $payment = $service->createFromInvoice(
        $invoice,
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->user->id,
        '2026-01-20'
    );

    expect($payment->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and($payment->customer_id)->toBe($invoice->customer_id)
        ->and($payment->amount)->toBe('1000.000')
        ->and($payment->journal_id)->toBe($this->journalBanque->id)
        ->and($payment->destination_account_id)->toBe($this->account532->id)
        ->and($payment->allocations)->toHaveCount(1)
        ->and((string) $payment->allocations->first()->amount)->toBe('1000.000')
        ->and((int) $payment->allocations->first()->invoice_id)->toBe($invoice->id);

    $service->post($payment->fresh(), $this->user->id);

    expect(fn () => $service->createFromInvoice(
        $invoice->fresh(),
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->user->id,
        '2026-01-20'
    ))->toThrow(InvalidArgumentException::class);
});

// ---------- updateDraft / allocate ----------

it('updates draft fields and syncs allocations', function () {
    $service = cpService();
    $invoiceA = createPostedInvoiceForPaymentTest($this);
    $invoiceB = createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this, ['amount' => '300.000']);

    $updated = $service->updateDraft($payment, [
        'payment_date' => '2026-01-25',
        'amount' => '400.000',
        'reference' => 'CHQ-42',
        'notes' => 'Paiement partiel',
        'allocations' => [
            ['invoice_id' => $invoiceA->id, 'amount' => '150.000'],
            ['invoice_id' => $invoiceB->id, 'amount' => '250.000'],
        ],
    ]);

    expect($updated->payment_date->toDateString())->toBe('2026-01-25')
        ->and($updated->amount)->toBe('400.000')
        ->and($updated->reference)->toBe('CHQ-42')
        ->and($updated->notes)->toBe('Paiement partiel')
        ->and($updated->allocations()->count())->toBe(2)
        ->and($updated->allocatedAmount())->toBe('400.000');
});

it('adds replaces and removes allocations on draft payments only', function () {
    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this, ['amount' => '400.000']);

    $payment = $service->allocate($payment->fresh(), $invoice->id, '400.000');
    expect($payment->allocations)->toHaveCount(1)
        ->and((string) $payment->allocations->first()->amount)->toBe('400.000');

    $payment = $service->allocate($payment->fresh(), $invoice->id, '250.000');
    expect($payment->allocations()->count())->toBe(1)
        ->and((string) $payment->allocations()->first()->amount)->toBe('250.000');

    expect(fn () => $service->allocate($payment->fresh(), $invoice->id, '999.000'))
        ->toThrow(InvalidArgumentException::class);

    $payment = $service->allocate($payment->fresh(), $invoice->id, '0');
    expect($payment->allocations)->toHaveCount(0);

    $posted = createDraftPaymentForTest($this, ['amount' => '100.000']);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->allocate($posted->fresh(), $invoice->id, '50.000'))
        ->toThrow(InvalidArgumentException::class);
});

// ---------- Posting ----------

it('posts a draft payment into a balanced posted journal entry', function () {
    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);

    $payment = createDraftPaymentForTest($this, [
        'amount' => '1000.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '1000.000']],
    ]);

    $posted = $service->post($payment->fresh(), $this->user->id);

    expect($posted->status)->toBe(CustomerPaymentStatus::POSTED)
        ->and($posted->posted_at)->not->toBeNull()
        ->and($posted->journal_entry_id)->not->toBeNull();

    $entry = $posted->journalEntry;
    expect($entry->status)->toBe(JournalEntryStatus::POSTED)
        ->and($entry->lines()->count())->toBe(2)
        ->and($entry->isBalanced())->toBeTrue()
        ->and($entry->reference)->toBe($payment->payment_number)
        ->and($entry->journal_id)->toBe($this->journalBanque->id);

    $debitLine = $entry->lines()->where('debit', '>', 0)->first();
    $creditLine = $entry->lines()->where('credit', '>', 0)->first();

    expect($debitLine->account_id)->toBe($this->account532->id)
        ->and((string) $debitLine->debit)->toBe('1000.000')
        ->and((string) $debitLine->credit)->toBe('0.000')
        ->and($creditLine->account_id)->toBe($this->account411->id)
        ->and((string) $creditLine->credit)->toBe('1000.000');
});

it('refuses posting an already posted payment', function () {
    $service = cpService();
    createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this);

    $service->post($payment->fresh(), $this->user->id);

    expect(fn () => $service->post($payment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class);
});

it('revalidates outstanding balances inside the posting transaction', function () {
    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);

    // Both drafts pass creation-time validation against a remaining of 1000.
    $p1 = createDraftPaymentForTest($this, [
        'amount' => '700.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '700.000']],
    ]);
    $p2 = createDraftPaymentForTest($this, [
        'amount' => '700.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '700.000']],
    ]);

    $service->post($p1->fresh(), $this->user->id);

    // The invoice remaining is now 300; posting p2 must fail and leave it untouched.
    expect(fn () => $service->post($p2->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class)
        ->and($p2->fresh()->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and($p2->fresh()->journal_entry_id)->toBeNull()
        ->and(cpService()->getInvoiceRemainingAmount($invoice->fresh()))->toBe('300.000');
});

it('posts an advance payment without allocations', function () {
    $service = cpService();
    $payment = createDraftPaymentForTest($this, ['amount' => '750.000']);

    $posted = $service->post($payment->fresh(), $this->user->id);

    $creditLine = $posted->journalEntry->lines()->where('credit', '>', 0)->first();

    expect($posted->status)->toBe(CustomerPaymentStatus::POSTED)
        ->and($posted->unallocatedAmount())->toBe('750.000')
        ->and($creditLine->account_id)->toBe($this->account411->id)
        ->and((string) $creditLine->credit)->toBe('750.000');
});

it('falls back to the default customer account when the customer has none', function () {
    $bareCustomer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL002',
        'name' => 'Client Sans Compte',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => null,
        'is_active' => true,
    ]);

    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_customer_account_id' => $this->account411->id,
    ]);

    $service = cpService();
    $payment = createDraftPaymentForTest($this, ['customer_id' => $bareCustomer->id]);
    $posted = $service->post($payment->fresh(), $this->user->id);

    $creditLine = $posted->journalEntry->lines()->where('credit', '>', 0)->first();

    expect($posted->status)->toBe(CustomerPaymentStatus::POSTED)
        ->and($creditLine->account_id)->toBe($this->account411->id);
});

it('refuses posting when no receivable account is configured', function () {
    $bareCustomer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL003',
        'name' => 'Client Orphelin',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => null,
        'is_active' => true,
    ]);

    $payment = createDraftPaymentForTest($this, ['customer_id' => $bareCustomer->id]);

    expect(fn () => cpService()->post($payment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class)
        ->and($payment->fresh()->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and($payment->fresh()->journal_entry_id)->toBeNull();
});

// ---------- Cancel / delete ----------

it('cancels a draft payment and refuses posted ones', function () {
    $service = cpService();
    createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this);

    $cancelled = $service->cancel($payment->fresh());

    expect($cancelled->status)->toBe(CustomerPaymentStatus::CANCELLED);

    $posted = createDraftPaymentForTest($this);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->cancel($posted->fresh()))->toThrow(InvalidArgumentException::class);
});

it('deletes a draft payment with its allocations and refuses posted ones', function () {
    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);

    $payment = createDraftPaymentForTest($this, [
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.000']],
    ]);

    $service->deleteDraft($payment->fresh());

    expect(CustomerPayment::find($payment->id))->toBeNull()
        ->and(DB::table('customer_payment_allocations')->where('customer_payment_id', $payment->id)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::POSTED);

    $posted = createDraftPaymentForTest($this);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->deleteDraft($posted->fresh()))->toThrow(InvalidArgumentException::class);
});

// ---------- Outstanding amounts ----------

it('computes the invoice remaining amount mixing credit notes and payments', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    $cn = cpCnService()->createDraft(array_merge([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journalVentes->id,
        'invoice_id' => $invoice->id,
        'credit_note_date' => '2026-01-18',
        'reason' => 'Retour marchandise',
        'currency' => 'TND',
        'notes' => null,
        'created_by' => $this->user->id,
        'lines' => makeCpCnLinesData($invoice, '200.000'),
    ]));

    cpCnService()->post($cn->fresh(), $this->user->id);

    $draftPayment = createDraftPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '500.000']],
    ]);

    // Draft payments do not reduce the outstanding balance.
    expect(cpService()->getInvoiceRemainingAmount($invoice->fresh()))->toBe('800.000');

    cpService()->post($draftPayment->fresh(), $this->user->id);

    expect(cpService()->getInvoiceRemainingAmount($invoice->fresh()))->toBe('300.000');
});

it('computes the customer outstanding balance across invoices', function () {
    $service = cpService();
    $invoiceA = createPostedInvoiceForPaymentTest($this);
    $invoiceB = createPostedInvoiceForPaymentTest($this);

    $payment = createDraftPaymentForTest($this, [
        'amount' => '400.000',
        'allocations' => [['invoice_id' => $invoiceA->id, 'amount' => '400.000']],
    ]);
    $service->post($payment->fresh(), $this->user->id);

    expect($service->getCustomerOutstandingBalance($this->customer->fresh()))->toBe('1600.000');
});

it('resolves the caisse journal for cash methods by fallback', function () {
    $resolved = cpService()->resolveJournal($this->methodEsp, $this->company->id, $this->fiscalYear->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($this->journalCaisse->id);
});

// ---------- Livewire pages ----------

it('renders the payments index page and filters by search', function () {
    $payment = createDraftPaymentForTest($this);

    $this->actingAs($this->user);

    Livewire::withQueryParams([])
        ->test(Index::class)
        ->assertOk()
        ->assertSee($payment->payment_number)
        ->set('search', 'INEXISTANT')
        ->assertSee('Aucun encaissement trouvé')
        ->set('search', $payment->payment_number)
        ->assertSee($payment->payment_number);
});

it('posts a draft payment from the index page', function () {
    createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this);

    $this->actingAs($this->user);

    Livewire::test(Index::class)
        ->call('post', $payment->id)
        ->assertHasNoErrors()
        ->assertRedirect(route('customer-payments.index'));

    expect($payment->fresh()->status)->toBe(CustomerPaymentStatus::POSTED)
        ->and($payment->fresh()->journal_entry_id)->not->toBeNull();
});

it('deletes a draft payment from the index page', function () {
    $payment = createDraftPaymentForTest($this);

    $this->actingAs($this->user);

    Livewire::test(Index::class)
        ->call('delete', $payment->id)
        ->assertHasNoErrors()
        ->assertRedirect(route('customer-payments.index'));

    expect(CustomerPayment::find($payment->id))->toBeNull();
});

it('renders the detail page and posts from it', function () {
    $service = cpService();
    createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this);

    $this->actingAs($this->user);

    Livewire::test(Show::class, ['paymentId' => $payment->id])
        ->assertOk()
        ->assertSee($payment->payment_number)
        ->call('post', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(CustomerPaymentStatus::POSTED);
});

it('preselects customer and remaining amount when creating from an invoice', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    $this->actingAs($this->user);

    Livewire::test(Create::class, ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSet('customer_id', (int) $invoice->customer_id)
        ->assertSet('amount', '1000.000');
});

it('creates a draft then a posted payment from the create page', function () {
    $invoice = createPostedInvoiceForPaymentTest($this);

    $this->actingAs($this->user);

    Livewire::test(Create::class)
        ->set('customer_id', (int) $this->customer->id)
        ->set('payment_method_id', (int) $this->methodVir->id)
        ->set('journal_id', (int) $this->journalBanque->id)
        ->set('destination_account_id', (int) $this->account532->id)
        ->set('payment_date', '2026-01-20')
        ->set('amount', '250.000')
        ->set('allocations.0.invoice_id', (int) $invoice->id)
        ->set('allocations.0.number', $invoice->invoice_number)
        ->set('allocations.0.date', '15/01/2026')
        ->set('allocations.0.total', '1000.000')
        ->set('allocations.0.credited', '0.000')
        ->set('allocations.0.paid', '0.000')
        ->set('allocations.0.remaining', '1000.000')
        ->set('allocations.0.amount', '250.000')
        ->call('save', false)
        ->assertHasNoErrors();

    $draft = CustomerPayment::query()->sole();
    expect($draft->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and($draft->allocations()->count())->toBe(1)
        ->and((string) $draft->allocations()->first()->amount)->toBe('250.000');

    Livewire::test(Create::class)
        ->set('customer_id', (int) $this->customer->id)
        ->set('payment_method_id', (int) $this->methodVir->id)
        ->set('journal_id', (int) $this->journalBanque->id)
        ->set('destination_account_id', (int) $this->account532->id)
        ->set('payment_date', '2026-01-21')
        ->set('amount', '100.000')
        ->call('save', true)
        ->assertHasNoErrors();

    expect(CustomerPayment::count())->toBe(2)
        ->and(CustomerPayment::orderByDesc('id')->first()->status)->toBe(CustomerPaymentStatus::POSTED);
});

it('loads and updates a draft from the edit page then posts it', function () {
    $service = cpService();
    $invoice = createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this, [
        'amount' => '300.000',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '300.000']],
    ]);

    $this->actingAs($this->user);

    Livewire::test(Edit::class, ['paymentId' => $payment->id])
        ->assertOk()
        ->assertSet('amount', '300.000')
        ->assertSee($invoice->invoice_number)
        ->set('reference', 'CHQ-789')
        ->set('allocations.0.amount', '200.000')
        ->call('save', false)
        ->assertHasNoErrors();

    $fresh = $payment->fresh();
    expect($fresh->reference)->toBe('CHQ-789')
        ->and($fresh->status)->toBe(CustomerPaymentStatus::DRAFT)
        ->and((string) $fresh->allocations()->first()->amount)->toBe('200.000')
        ->and($fresh->unallocatedAmount())->toBe('100.000');

    Livewire::test(Edit::class, ['paymentId' => $payment->id])
        ->call('save', true)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(CustomerPaymentStatus::POSTED);
});

// ---------- Policy ----------

it('forbids non-admin members from managing payments but allows viewing', function () {
    $member = User::factory()->create();
    $member->email_verified_at = now();
    $member->save();
    $this->company->users()->attach($member, ['role' => 'member', 'is_active' => true]);

    createPostedInvoiceForPaymentTest($this);
    $payment = createDraftPaymentForTest($this);

    expect($member->cannot('create', [CustomerPayment::class, $this->company]))->toBeTrue()
        ->and($member->cannot('update', $payment))->toBeTrue()
        ->and($member->cannot('delete', $payment))->toBeTrue()
        ->and($member->cannot('post', $payment))->toBeTrue()
        ->and($member->can('viewAny', CustomerPayment::class))->toBeTrue()
        ->and($member->can('view', $payment))->toBeTrue()
        ->and($this->user->can('post', $payment))->toBeTrue();
});
