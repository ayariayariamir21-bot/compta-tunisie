<?php

use App\Enums\JournalEntryStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Livewire\SupplierPayments\Create;
use App\Livewire\SupplierPayments\Edit;
use App\Livewire\SupplierPayments\Index;
use App\Livewire\SupplierPayments\Show;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
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
use App\Services\Accounting\TrialBalanceService;
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

    $this->journalCaisse = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'CSQ',
        'name' => 'Caisses',
        'type' => 'caisse',
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

    $this->account571 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '571000',
        'name' => 'Caisse',
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
        'name' => 'Fournisseur Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
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

    $this->actingAs($this->user);
});

// ---------- Helpers ----------

function spService(): SupplierPaymentService
{
    return new SupplierPaymentService(new SupplierPaymentPostingService(
        new JournalEntryService
    ));
}

/**
 * Simple purchase invoice with round numbers: 1000 units at 1.000, no discount, no tax.
 */
function makeSimplePurchaseInvoiceData($test): array
{
    return [
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journalAchats->id,
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

function createPostedPurchaseInvoiceForPaymentTest($test, array $overrides = []): PurchaseInvoice
{
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));

    $data = makeSimplePurchaseInvoiceData($test);
    $data['lines'][0] = array_merge($data['lines'][0], $overrides);

    $invoice = $service->createDraft($data);
    $service->post($invoice->fresh(), $test->user->id);

    return $invoice->fresh();
}

function makeSupplierPaymentData($test, array $overrides = []): array
{
    return array_merge([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->methodVir->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => '2026-01-20',
        'amount' => '500.000',
        'currency' => 'TND',
        'reference' => 'VIR-001',
        'notes' => null,
        'created_by' => $test->user->id,
        'allocations' => [],
    ], $overrides);
}

function createDraftSupplierPaymentForTest($test, array $overrides = []): SupplierPayment
{
    return spService()->createDraft(makeSupplierPaymentData($test, $overrides));
}

// ---------- Numbering ----------

it('generates sequential payment numbers scoped per company', function () {
    $service = spService();

    expect($service->generatePaymentNumber($this->company->id))->toBe('REG-ACH-'.now()->year.'-000001');

    createDraftSupplierPaymentForTest($this);

    expect($service->generatePaymentNumber($this->company->id))->toBe('REG-ACH-'.now()->year.'-000002');
});

// ---------- createDraft ----------

it('creates a draft payment with allocations and computes allocated and unallocated amounts', function () {
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '400.000']],
    ]);

    expect($payment->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($payment->payment_number)->toBe('REG-ACH-'.now()->year.'-000001')
        ->and($payment->amount)->toBe('500.000')
        ->and($payment->currency)->toBe('TND')
        ->and($payment->journal_id)->toBe($this->journalBanque->id)
        ->and($payment->destination_account_id)->toBe($this->account532->id)
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocatedAmount())->toBe('400.000')
        ->and($payment->unallocatedAmount())->toBe('100.000');
});

it('auto-resolves the banque journal for bank transfer methods when omitted', function () {
    createPostedPurchaseInvoiceForPaymentTest($this);

    $data = makeSupplierPaymentData($this);
    unset($data['journal_id']);

    $payment = spService()->createDraft($data);

    expect($payment->fresh()->journal_id)->toBe($this->journalBanque->id);
});

it('uses the configured default bank account as destination when omitted', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $data = makeSupplierPaymentData($this);
    unset($data['destination_account_id']);

    $payment = spService()->createDraft($data);

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

    $data = makeSupplierPaymentData($this, ['payment_method_id' => $other->id]);
    unset($data['journal_id']);

    expect(fn () => spService()->createDraft($data))->toThrow(InvalidArgumentException::class);
});

it('refuses allocations exceeding the invoice remaining balance', function () {
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    expect(fn () => createDraftSupplierPaymentForTest($this, [
        'amount' => '2000.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '1500.000']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('refuses allocations totalling more than the payment amount', function () {
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    expect(fn () => createDraftSupplierPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '600.000']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('refuses allocating a non-posted invoice', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(
        new JournalEntryService
    ));

    $draftInvoice = $service->createDraft(makeSimplePurchaseInvoiceData($this));

    expect(fn () => createDraftSupplierPaymentForTest($this, [
        'allocations' => [['purchase_invoice_id' => $draftInvoice->id, 'amount' => '100.000']],
    ]))->toThrow(InvalidArgumentException::class)
        ->and(PurchaseInvoice::find($draftInvoice->id)->status)->not->toBe(PurchaseInvoiceStatus::POSTED);
});

it('refuses an inactive supplier', function () {
    $this->supplier->update(['is_active' => false]);

    expect(fn () => createDraftSupplierPaymentForTest($this))->toThrow(InvalidArgumentException::class);
});

it('refuses a supplier from another company', function () {
    $otherCompany = Company::create([
        'name' => 'Other Company',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $foreignSupplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'code' => 'FO002',
        'name' => 'Fournisseur Étranger',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    expect(fn () => createDraftSupplierPaymentForTest($this, ['supplier_id' => $foreignSupplier->id]))
        ->toThrow(InvalidArgumentException::class);
});

// ---------- createFromInvoice ----------

it('creates a draft from an invoice preselecting the remaining amount and allocation', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_journal_id' => $this->journalBanque->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = $service->createFromInvoice(
        $invoice,
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->user->id,
        '2026-01-20'
    );

    expect($payment->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($payment->supplier_id)->toBe($invoice->supplier_id)
        ->and($payment->amount)->toBe('1000.000')
        ->and($payment->journal_id)->toBe($this->journalBanque->id)
        ->and($payment->destination_account_id)->toBe($this->account532->id)
        ->and($payment->allocations)->toHaveCount(1)
        ->and((string) $payment->allocations->first()->amount)->toBe('1000.000')
        ->and((int) $payment->allocations->first()->purchase_invoice_id)->toBe($invoice->id);

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
    $service = spService();
    $invoiceA = createPostedPurchaseInvoiceForPaymentTest($this);
    $invoiceB = createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this, ['amount' => '300.000']);

    $updated = $service->updateDraft($payment, [
        'payment_date' => '2026-01-25',
        'amount' => '400.000',
        'reference' => 'CHQ-42',
        'notes' => 'Paiement partiel',
        'allocations' => [
            ['purchase_invoice_id' => $invoiceA->id, 'amount' => '150.000'],
            ['purchase_invoice_id' => $invoiceB->id, 'amount' => '250.000'],
        ],
    ]);

    expect($updated->payment_date->toDateString())->toBe('2026-01-25')
        ->and($updated->amount)->toBe('400.000')
        ->and($updated->reference)->toBe('CHQ-42')
        ->and($updated->notes)->toBe('Paiement partiel')
        ->and($updated->allocations()->count())->toBe(2)
        ->and($updated->allocatedAmount())->toBe('400.000');
});

it('refuses updating a posted payment', function () {
    $service = spService();
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);
    $service->post($payment->fresh(), $this->user->id);

    expect(fn () => $service->updateDraft($payment->fresh(), ['amount' => '900.000']))
        ->toThrow(InvalidArgumentException::class)
        ->and($payment->fresh()->amount)->toBe('500.000');
});

it('adds replaces and removes allocations on draft payments only', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this, ['amount' => '400.000']);

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

    $posted = createDraftSupplierPaymentForTest($this, ['amount' => '100.000']);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->allocate($posted->fresh(), $invoice->id, '50.000'))
        ->toThrow(InvalidArgumentException::class);
});

// ---------- Cancel / delete ----------

it('cancels a draft payment and refuses posted ones', function () {
    $service = spService();
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    $cancelled = $service->cancel($payment->fresh());

    expect($cancelled->status)->toBe(SupplierPaymentStatus::CANCELLED);

    $posted = createDraftSupplierPaymentForTest($this);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->cancel($posted->fresh()))->toThrow(InvalidArgumentException::class);
});

it('deletes a draft payment with its allocations and refuses posted ones', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '100.000']],
    ]);

    $service->deleteDraft($payment->fresh());

    expect(SupplierPayment::find($payment->id))->toBeNull()
        ->and(DB::table('supplier_payment_allocations')->where('supplier_payment_id', $payment->id)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(PurchaseInvoiceStatus::POSTED);

    $posted = createDraftSupplierPaymentForTest($this);
    $service->post($posted->fresh(), $this->user->id);

    expect(fn () => $service->deleteDraft($posted->fresh()))->toThrow(InvalidArgumentException::class);
});

// ---------- Outstanding amounts ----------

it('computes the invoice remaining amount excluding draft payments', function () {
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    createDraftSupplierPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '500.000']],
    ]);

    // Draft payments do not reduce the outstanding balance.
    expect(spService()->getInvoiceRemainingAmount($invoice->fresh()))->toBe('1000.000');
});

it('computes the supplier outstanding balance across invoices', function () {
    $service = spService();
    $invoiceA = createPostedPurchaseInvoiceForPaymentTest($this);
    $invoiceB = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '400.000',
        'allocations' => [['purchase_invoice_id' => $invoiceA->id, 'amount' => '400.000']],
    ]);
    $service->post($payment->fresh(), $this->user->id);

    expect($service->getSupplierOutstandingBalance($this->supplier->fresh()))->toBe('1600.000');
});

it('resolves the caisse journal for cash methods by fallback', function () {
    $resolved = spService()->resolveJournal($this->methodEsp, $this->company->id, $this->fiscalYear->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($this->journalCaisse->id);
});

it('shows the supplier account movements in the general ledger after settlement', function () {
    // PI: credit 401 by 1190 (HT 1000 + VAT 190), then payment: debit 401 by 1190.
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this, [
        'tax_rate_id' => $this->taxRate->id,
    ]);

    $service = spService();
    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '1190.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '1190.000']],
    ]);
    $service->post($payment->fresh(), $this->user->id);

    $ledger401 = app(GeneralLedgerService::class)->getAccountLedger(
        $this->account401,
        $this->company,
        $this->fiscalYear,
        []
    );

    expect($ledger401)->toHaveCount(2);

    $credits = collect($ledger401)->sum(fn ($line) => (float) $line->credit);
    $debits = collect($ledger401)->sum(fn ($line) => (float) $line->debit);

    expect((string) number_format($credits, 3, '.', ''))->toBe('1190.000')
        ->and((string) number_format($debits, 3, '.', ''))->toBe('1190.000');
});

// ---------- Livewire components ----------

it('renders the payments index page and filters by search', function () {
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    Livewire::test(Index::class)
        ->assertOk()
        ->set('search', 'INEXISTANT')
        ->assertSee('Aucun règlement trouvé')
        ->set('search', '')
        ->assertSee($payment->payment_number);
});

it('posts a payment from the index page', function () {
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    Livewire::test(Index::class)
        ->call('post', $payment->id);

    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::POSTED)
        ->and($payment->fresh()->journal_entry_id)->not->toBeNull();
});

it('cancels a payment from the index page', function () {
    $payment = createDraftSupplierPaymentForTest($this);

    Livewire::test(Index::class)
        ->call('cancel', $payment->id);

    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::CANCELLED);
});

it('deletes a draft payment from the index page', function () {
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    Livewire::test(Index::class)
        ->call('delete', $payment->id);

    expect(SupplierPayment::find($payment->id))->toBeNull();
});

it('shows a posted payment with its journal entry', function () {
    $service = spService();
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);
    $service->post($payment->fresh(), $this->user->id);

    Livewire::test(Show::class, ['supplierPaymentId' => $payment->id])
        ->assertOk()
        ->assertSee('Comptabilisé')
        ->assertSee($payment->payment_number);
});

it('creates a draft from an invoice via the create page', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_journal_id' => $this->journalBanque->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $component = Livewire::test(Create::class, ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSet('supplier_id', $this->supplier->id)
        ->assertSet('amount', '1000.000')
        ->assertCount('allocations', 1);

    expect($component->instance()->allocations[0]['purchase_invoice_id'])->toBe((int) $invoice->id);
});

it('creates a draft then posts it through the create page flow', function () {
    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_bank_journal_id' => $this->journalBanque->id,
        'default_bank_account_id' => $this->account532->id,
    ]);

    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    Livewire::test(Create::class, ['invoice' => $invoice->id])
        ->set('payment_method_id', (int) $this->methodVir->id)
        ->set('payment_date', '2026-01-20')
        ->call('save', false);

    $payment = SupplierPayment::first();
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(SupplierPaymentStatus::DRAFT);

    Livewire::test(Edit::class, ['supplierPaymentId' => $payment->id])
        ->assertOk()
        ->call('save', true);

    expect($payment->fresh()->status)->toBe(SupplierPaymentStatus::POSTED);
});

it('loads supplier invoices on the edit page and updates the draft', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '400.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '400.000']],
    ]);

    $component = Livewire::test(Edit::class, ['supplierPaymentId' => $payment->id])
        // The already-allocated 400 must count as paid for the remaining calculation.
        ->assertSee('600.000')
        ->set('reference', 'CHQ-777')
        ->call('save', false);

    expect($payment->fresh()->reference)->toBe('CHQ-777')
        ->and($payment->fresh()->status)->toBe(SupplierPaymentStatus::DRAFT);
});

it('refuses rendering the edit page for non-draft payments', function () {
    $service = spService();
    $payment = createDraftSupplierPaymentForTest($this);
    $service->post($payment->fresh(), $this->user->id);

    // The update policy rejects non-draft payments before the render guard runs.
    $this->get(route('supplier-payments.edit', ['supplierPaymentId' => $payment->id]))->assertForbidden();
});

// ---------- HTTP smoke ----------

it('redirects guests to the login page', function () {
    auth()->logout();

    $this->get(route('supplier-payments.index'))->assertRedirect(route('login'));
});

it('serves the supplier payment pages to an admin', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    $this->get(route('supplier-payments.index'))->assertOk();
    $this->get(route('supplier-payments.create'))->assertOk();
    $this->get(route('supplier-payments.create', ['invoice' => $invoice->id]))->assertOk();
    $this->get(route('supplier-payments.show', ['supplierPaymentId' => $payment->id]))->assertOk();
    $this->get(route('supplier-payments.edit', ['supplierPaymentId' => $payment->id]))->assertOk();
});

it('returns 404 for payments of another company or unknown ids', function () {
    $otherCompany = Company::create([
        'name' => 'Other Co',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $foreignPayment = SupplierPayment::create([
        'company_id' => $otherCompany->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,

        'accounting_period_id' => $this->period->id,
        'payment_method_id' => $this->methodVir->id,
        'journal_id' => $this->journalBanque->id,
        'destination_account_id' => $this->account532->id,
        'payment_number' => 'REG-ACH-X1',
        'payment_date' => '2026-01-20',
        'amount' => '10.000',
        'currency' => 'TND',
        'status' => SupplierPaymentStatus::DRAFT,
        'created_by' => $this->user->id,
    ]);

    $this->get(route('supplier-payments.show', ['supplierPaymentId' => $foreignPayment->id]))->assertNotFound();
    $this->get(route('supplier-payments.show', ['supplierPaymentId' => 999999]))->assertNotFound();
});

// ---------- Policy ----------

it('forbids member-role users from managing payments', function () {
    $member = User::factory()->create(['email_verified_at' => now()]);
    $this->company->users()->attach($member, ['role' => 'member', 'is_active' => true]);
    $this->actingAs($member);
    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);

    $service = spService();
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    expect($member->can('viewAny', SupplierPayment::class))->toBeTrue()
        ->and($member->can('view', $payment))->toBeTrue()
        ->and($member->can('create', [SupplierPayment::class, $this->company]))->toBeFalse()
        ->and($member->can('createFromInvoice', [SupplierPayment::class, $payment]))->toBeFalse()
        ->and($this->user->can('create', [SupplierPayment::class, $this->company]))->toBeTrue()
        ->and($this->user->can('update', $payment))->toBeTrue()
        ->and($member->can('delete', $payment))->toBeFalse()
        ->and($member->can('post', $payment))->toBeFalse()
        ->and($member->can('cancel', $payment))->toBeFalse();
});

// ---------- Posting ----------

it('posts a draft payment into a balanced posted journal entry', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '1000.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '1000.000']],
    ]);

    $posted = $service->post($payment->fresh(), $this->user->id);

    expect($posted->status)->toBe(SupplierPaymentStatus::POSTED)
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

    expect($debitLine->account_id)->toBe($this->account401->id)
        ->and((string) $debitLine->debit)->toBe('1000.000')
        ->and((string) $debitLine->credit)->toBe('0.000')
        ->and($creditLine->account_id)->toBe($this->account532->id)
        ->and((string) $creditLine->credit)->toBe('1000.000');
});

it('settles a vat invoice fully: HT 1000, TVA 190, TTC 1190', function () {
    $service = spService();
    // 1000 × 1.000 with 19% VAT → total TTC 1190.
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this, [
        'tax_rate_id' => $this->taxRate->id,
    ]);
    expect((string) $invoice->total)->toBe('1190.000');

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '1190.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '1190.000']],
    ]);

    $posted = $service->post($payment->fresh(), $this->user->id);

    expect($posted->status)->toBe(SupplierPaymentStatus::POSTED);

    $debitLine = $posted->journalEntry->lines()->where('debit', '>', 0)->first();
    $creditLine = $posted->journalEntry->lines()->where('credit', '>', 0)->first();

    expect($debitLine->account_id)->toBe($this->account401->id)
        ->and((string) $debitLine->debit)->toBe('1190.000')
        ->and($creditLine->account_id)->toBe($this->account532->id)
        ->and((string) $creditLine->credit)->toBe('1190.000')
        ->and($service->getInvoiceRemainingAmount($invoice->fresh()))->toBe('0.000');

    // Trial balance remains balanced after the full purchase + payment cycle.
    $totals = app(TrialBalanceService::class)->getGrandTotals($this->company, $this->fiscalYear);
    expect((string) $totals['total_debit'])->toBe((string) $totals['total_credit']);
});

it('leaves the remaining balance after a partial payment', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    $payment = createDraftSupplierPaymentForTest($this, [
        'amount' => '500.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '500.000']],
    ]);

    $posted = $service->post($payment->fresh(), $this->user->id);

    expect($posted->status)->toBe(SupplierPaymentStatus::POSTED)
        ->and($service->getInvoiceRemainingAmount($invoice->fresh()))->toBe('500.000');
});

it('refuses posting an already posted payment', function () {
    $service = spService();
    createPostedPurchaseInvoiceForPaymentTest($this);
    $payment = createDraftSupplierPaymentForTest($this);

    $service->post($payment->fresh(), $this->user->id);

    expect(fn () => $service->post($payment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class);
});

it('revalidates outstanding balances inside the posting transaction', function () {
    $service = spService();
    $invoice = createPostedPurchaseInvoiceForPaymentTest($this);

    // Both drafts pass creation-time validation against a remaining of 1000.
    $p1 = createDraftSupplierPaymentForTest($this, [
        'amount' => '700.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '700.000']],
    ]);
    $p2 = createDraftSupplierPaymentForTest($this, [
        'amount' => '700.000',
        'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => '700.000']],
    ]);

    $service->post($p1->fresh(), $this->user->id);

    // The invoice remaining is now 300; posting p2 must fail and leave it untouched.
    expect(fn () => $service->post($p2->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class)
        ->and($p2->fresh()->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($p2->fresh()->journal_entry_id)->toBeNull()
        ->and(spService()->getInvoiceRemainingAmount($invoice->fresh()))->toBe('300.000');
});

it('posts an advance payment without allocations', function () {
    $service = spService();
    $payment = createDraftSupplierPaymentForTest($this, ['amount' => '750.000']);

    $posted = $service->post($payment->fresh(), $this->user->id);

    $debitLine = $posted->journalEntry->lines()->where('debit', '>', 0)->first();

    expect($posted->status)->toBe(SupplierPaymentStatus::POSTED)
        ->and($posted->unallocatedAmount())->toBe('750.000')
        ->and($debitLine->account_id)->toBe($this->account401->id)
        ->and((string) $debitLine->debit)->toBe('750.000');
});

it('falls back to the default supplier account when the supplier has none', function () {
    $bareSupplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO003',
        'name' => 'Sans Compte',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    CompanyAccountingSetting::create([
        'company_id' => $this->company->id,
        'default_supplier_account_id' => $this->account401->id,
    ]);

    $service = spService();
    $payment = createDraftSupplierPaymentForTest($this, ['supplier_id' => $bareSupplier->id]);
    $posted = $service->post($payment->fresh(), $this->user->id);

    $debitLine = $posted->journalEntry->lines()->where('debit', '>', 0)->first();

    expect($posted->status)->toBe(SupplierPaymentStatus::POSTED)
        ->and($debitLine->account_id)->toBe($this->account401->id);
});

it('refuses posting when no payable account is configured', function () {
    $bareSupplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO004',
        'name' => 'Fournisseur Orphelin',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'is_active' => true,
    ]);

    $payment = createDraftSupplierPaymentForTest($this, ['supplier_id' => $bareSupplier->id]);

    expect(fn () => spService()->post($payment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class)
        ->and($payment->fresh()->status)->toBe(SupplierPaymentStatus::DRAFT)
        ->and($payment->fresh()->journal_entry_id)->toBeNull();
});

it('refuses posting in a closed fiscal year or closed period', function () {
    createPostedPurchaseInvoiceForPaymentTest($this);

    $closedFyPayment = createDraftSupplierPaymentForTest($this);
    $this->fiscalYear->update(['is_closed' => true]);
    expect(fn () => spService()->post($closedFyPayment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class);
    $this->fiscalYear->update(['is_closed' => false]);

    $closedPeriodPayment = createDraftSupplierPaymentForTest($this);
    $this->period->update(['is_open' => false, 'is_closed' => true]);
    expect(fn () => spService()->post($closedPeriodPayment->fresh(), $this->user->id))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a payment date outside the accounting period', function () {
    expect(fn () => createDraftSupplierPaymentForTest($this, ['payment_date' => '2026-02-20']))
        ->toThrow(InvalidArgumentException::class);
});

// MARKER3B
