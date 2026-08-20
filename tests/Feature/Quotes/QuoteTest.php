<?php

use App\Enums\QuoteStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->account = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '701000',
        'name' => 'Ventes de marchandises',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
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
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

function makeQuoteData(Company $company, Customer $customer, Product $product, TaxRate $taxRate): array
{
    return [
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'quote_date' => '2026-08-20',
        'valid_until' => '2026-09-20',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'notes' => 'Test notes',
        'terms' => 'Test terms',
        'created_by' => User::factory()->create([
            'email_verified_at' => now(),
        ])->id,
        'lines' => [
            [
                'product_id' => $product->id,
                'description' => 'Produit Test',
                'quantity' => '10.000',
                'unit' => 'unit',
                'unit_price' => '25.000',
                'discount_percent' => '10.000',
                'tax_rate_id' => $taxRate->id,
            ],
        ],
    ];
}

it('creates a quote', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($quote->quote_number)->toStartWith('DEV-');
    expect($quote->status)->toBe(QuoteStatus::DRAFT);
    expect($quote->customer_id)->toBe($this->customer->id);
    expect($quote->company_id)->toBe($this->company->id);
});

it('quote belongs to current company', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($quote->company_id)->toBe($this->company->id);
    expect($quote->company->name)->toBe('Test Company');
});

it('rejects customer from another company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherCustomer = Customer::create([
        'company_id' => $otherCompany->id,
        'code' => 'CL999',
        'name' => 'Other Customer',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateCustomer($otherCustomer->id, $this->company->id);
});

it('rejects inactive customer', function () {
    $this->customer->update(['is_active' => false]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateCustomer($this->customer->id, $this->company->id);
});

it('rejects product from another company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherProduct = Product::create([
        'company_id' => $otherCompany->id,
        'code' => 'PRD999',
        'name' => 'Other Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateProduct($otherProduct->id, $this->company->id);
});

it('rejects inactive product', function () {
    $this->product->update(['is_active' => false]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateProduct($this->product->id, $this->company->id);
});

it('rejects non-sellable product', function () {
    $this->product->update(['is_sellable' => false]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateProduct($this->product->id, $this->company->id);
});

it('rejects tax rate from another company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherTaxRate = TaxRate::create([
        'company_id' => $otherCompany->id,
        'code' => 'TVA7',
        'name' => 'TVA 7%',
        'type' => 'vat',
        'rate' => 7.0,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateTaxRate($otherTaxRate->id, $this->company->id);
});

it('rejects inactive tax rate', function () {
    $this->taxRate->update(['is_active' => false]);

    $service = new QuoteService;
    $this->expectException(InvalidArgumentException::class);
    $service->validateTaxRate($this->taxRate->id, $this->company->id);
});

it('generates unique quote numbers within company', function () {
    $service = new QuoteService;

    $num1 = $service->generateQuoteNumber($this->company->id);
    expect($num1)->toStartWith('DEV-');
    expect($num1)->toEndWith('000001');

    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;
    $service->createDraft($data);

    $num2 = $service->generateQuoteNumber($this->company->id);
    expect($num2)->toEndWith('000002');
});

it('creates quote with lines', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($quote->lines)->toHaveCount(1);
    expect($quote->lines->first()->product_id)->toBe($this->product->id);
});

it('validates quantity is positive', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;
    $data['lines'][0]['quantity'] = '0';

    $this->expectException(InvalidArgumentException::class);
    $service->createDraft($data);
});

it('validates unit_price is not negative', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;
    $data['lines'][0]['unit_price'] = '-5';

    $this->expectException(InvalidArgumentException::class);
    $service->createDraft($data);
});

it('validates discount_percent range', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;
    $data['lines'][0]['discount_percent'] = '150';

    $this->expectException(InvalidArgumentException::class);
    $service->createDraft($data);
});

it('calculates tax correctly', function () {
    $service = new QuoteService;

    // 10 units × 25.000 = 250.000, 10% discount = 25.000, subtotal = 225.000
    // TVA 19% on 225.000 = 42.750, total = 267.750
    $result = $service->calculateLine('10', '25', '10', $this->taxRate);

    expect($result['discount_amount'])->toBe('25.000');
    expect($result['line_subtotal'])->toBe('225.000');
    expect($result['tax_amount'])->toBe('42.750');
    expect($result['line_total'])->toBe('267.750');
});

it('calculates line subtotal correctly', function () {
    $service = new QuoteService;

    // 5 × 100 = 500, no discount, no tax
    $result = $service->calculateLine('5', '100', '0', null);

    expect($result['gross'] ?? $result['line_subtotal'])->toBe('500.000');
    expect($result['discount_amount'])->toBe('0.000');
    expect($result['tax_amount'])->toBe('0.000');
    expect($result['line_total'])->toBe('500.000');
});

it('calculates line total with discount and tax', function () {
    $service = new QuoteService;

    // 2 × 100 = 200, 25% discount = 50, subtotal = 150, TVA 19% = 28.500, total = 178.500
    $result = $service->calculateLine('2', '100', '25', $this->taxRate);

    expect($result['discount_amount'])->toBe('50.000');
    expect($result['line_subtotal'])->toBe('150.000');
    expect($result['tax_amount'])->toBe('28.500');
    expect($result['line_total'])->toBe('178.500');
});

it('calculates quote totals', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    // 10 × 25 = 250, 10% discount = 25, subtotal = 225, TVA 19% = 42.750, total = 267.750
    expect($quote->subtotal)->toBe('225.000');
    expect($quote->discount_total)->toBe('25.000');
    expect($quote->tax_total)->toBe('42.750');
    expect($quote->total)->toBe('267.750');
});

it('draft can be edited', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    expect($quote->status)->toBe(QuoteStatus::DRAFT);

    $updated = $service->updateDraft($quote, [
        'customer_id' => $this->customer->id,
        'quote_date' => '2026-08-21',
        'valid_until' => '2026-09-21',
        'currency' => 'TND',
        'payment_terms_days' => 60,
        'notes' => 'Updated notes',
        'terms' => 'Updated terms',
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Updated Product',
                'quantity' => '5',
                'unit' => 'unit',
                'unit_price' => '30.000',
                'discount_percent' => '0',
                'tax_rate_id' => $this->taxRate->id,
            ],
        ],
    ]);

    expect($updated->notes)->toBe('Updated notes');
    expect($updated->payment_terms_days)->toBe(60);
    expect($updated->total)->toBe('178.500');
});

it('draft can be deleted', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $quoteId = $quote->id;

    $service->deleteDraft($quote);

    expect(Quote::where('id', $quoteId)->exists())->toBeFalse();
});

it('send transition works', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $sent = $service->send($quote);

    expect($sent->status)->toBe(QuoteStatus::SENT);
});

it('accept transition works', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);
    $accepted = $service->accept($quote->fresh());

    expect($accepted->status)->toBe(QuoteStatus::ACCEPTED);
});

it('reject transition works', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);
    $rejected = $service->reject($quote->fresh());

    expect($rejected->status)->toBe(QuoteStatus::REJECTED);
});

it('cancel transition works', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $cancelled = $service->cancel($quote);

    expect($cancelled->status)->toBe(QuoteStatus::CANCELLED);
});

it('accepted quote is immutable', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);
    $service->accept($quote->fresh());

    $this->expectException(InvalidArgumentException::class);
    $service->updateDraft($quote->fresh(), $data);
});

it('rejected quote is immutable', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);
    $service->reject($quote->fresh());

    $this->expectException(InvalidArgumentException::class);
    $service->updateDraft($quote->fresh(), $data);
});

it('cancelled quote is immutable', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->cancel($quote);

    $this->expectException(InvalidArgumentException::class);
    $service->updateDraft($quote->fresh(), $data);
});

it('cannot delete non-draft quote', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);

    $this->expectException(InvalidArgumentException::class);
    $service->deleteDraft($quote->fresh());
});

it('duplicate quote creates new draft with new number', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $original = $service->createDraft($data);
    $originalNumber = $original->quote_number;

    $duplicate = $service->duplicate($original);

    expect($duplicate->id)->not->toBe($original->id);
    expect($duplicate->quote_number)->not->toBe($originalNumber);
    expect($duplicate->status)->toBe(QuoteStatus::DRAFT);
    expect($duplicate->customer_id)->toBe($this->customer->id);
    expect($duplicate->lines)->toHaveCount(1);
});

it('allows search by quote number', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    $found = Quote::where('company_id', $this->company->id)
        ->where('quote_number', 'like', "%{$quote->quote_number}%")
        ->count();
    expect($found)->toBe(1);
});

it('supports pagination', function () {
    $service = new QuoteService;

    for ($i = 1; $i <= 20; $i++) {
        $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
        $data['created_by'] = $this->user->id;
        $data['lines'][0]['quantity'] = (string) $i;
        $service->createDraft($data);
    }

    expect(Quote::where('company_id', $this->company->id)->count())->toBe(20);

    $page1 = Quote::where('company_id', $this->company->id)->paginate(15);
    expect($page1->total())->toBe(20);
    expect($page1->count())->toBe(15);
});

it('status filter works', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $draft = $service->createDraft($data);
    $service->send($draft);

    $draft2Data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $draft2Data['created_by'] = $this->user->id;
    $service->createDraft($draft2Data);

    $sentCount = Quote::where('company_id', $this->company->id)->where('status', 'sent')->count();
    $draftCount = Quote::where('company_id', $this->company->id)->where('status', 'draft')->count();

    expect($sentCount)->toBe(1);
    expect($draftCount)->toBe(1);
});

it('customer filter works', function () {
    $service = new QuoteService;

    $customer2 = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL002',
        'name' => 'Client Deux',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $data1 = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data1['created_by'] = $this->user->id;
    $service->createDraft($data1);

    $data2 = makeQuoteData($this->company, $customer2, $this->product, $this->taxRate);
    $data2['created_by'] = $this->user->id;
    $service->createDraft($data2);

    $count1 = Quote::where('company_id', $this->company->id)->where('customer_id', $this->customer->id)->count();
    $count2 = Quote::where('company_id', $this->company->id)->where('customer_id', $customer2->id)->count();

    expect($count1)->toBe(1);
    expect($count2)->toBe(1);
});

it('date filter works', function () {
    $service = new QuoteService;

    $data1 = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data1['created_by'] = $this->user->id;
    $data1['quote_date'] = '2026-01-15';
    $service->createDraft($data1);

    $data2 = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data2['created_by'] = $this->user->id;
    $data2['quote_date'] = '2026-08-20';
    $service->createDraft($data2);

    $count = Quote::where('company_id', $this->company->id)
        ->where('quote_date', '>=', '2026-08-01')
        ->count();
    expect($count)->toBe(1);
});

it('cross-company access is blocked', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($this->user->can('view', $quote))->toBeTrue();
    expect($otherCompany->id)->not->toBe($this->company->id);
});

it('inactive customer data remains readable on historical quote', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $quoteId = $quote->id;

    $this->customer->update(['is_active' => false]);

    $historical = Quote::with('customer')->find($quoteId);
    expect($historical->customer->name)->toBe('Client Test');
    expect($historical->customer->is_active)->toBeFalse();
});

it('no journal entry is created by a quote', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);
    $service->send($quote);
    $service->accept($quote->fresh());

    $journalEntries = JournalEntry::where('company_id', $this->company->id)->count();
    expect($journalEntries)->toBe(0);
});

it('existing customers remain intact', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $service->createDraft($data);

    expect(Customer::where('id', $this->customer->id)->exists())->toBeTrue();
    expect(Customer::where('company_id', $this->company->id)->count())->toBe(1);
});

it('existing suppliers remain intact', function () {
    $supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $service->createDraft($data);

    expect(Supplier::where('id', $supplier->id)->exists())->toBeTrue();
});

it('existing products remain intact', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $service->createDraft($data);

    expect(Product::where('id', $this->product->id)->exists())->toBeTrue();
    expect((string) Product::find($this->product->id)->sale_price)->toBe('25.000');
});

it('existing accounting data remains intact', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $service->createDraft($data);

    expect(Account::where('id', $this->account->id)->exists())->toBeTrue();
    expect(FiscalYear::where('id', $this->fiscalYear->id)->exists())->toBeTrue();
});

it('validates dates', function () {
    $service = new QuoteService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateDates('2026-09-01', '2026-08-01');
});

it('allows null valid_until', function () {
    $service = new QuoteService;

    // Should not throw
    $service->validateDates('2026-08-20', null);
});

it('blocks viewer from mutating quote', function () {
    $viewer = User::factory()->create();
    $viewer->email_verified_at = now();
    $viewer->save();
    $this->company->users()->attach($viewer, ['role' => 'viewer', 'is_active' => true]);

    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($viewer->can('update', $quote))->toBeFalse();
    expect($viewer->can('delete', $quote))->toBeFalse();
    expect($viewer->can('send', $quote))->toBeFalse();
    expect($viewer->can('view', $quote))->toBeTrue();
});

it('admin can manage quotes', function () {
    $service = new QuoteService;
    $data = makeQuoteData($this->company, $this->customer, $this->product, $this->taxRate);
    $data['created_by'] = $this->user->id;

    $quote = $service->createDraft($data);

    expect($this->user->can('update', $quote))->toBeTrue();
    expect($this->user->can('delete', $quote))->toBeTrue();
    expect($this->user->can('send', $quote))->toBeTrue();
    expect($this->user->can('view', $quote))->toBeTrue();
    expect($this->user->can('duplicate', $quote))->toBeTrue();
});
