<?php

use App\Enums\ProductType;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\ProductService;
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

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

it('creates a product', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->code)->toBe('PRD001');
    expect($product->name)->toBe('Produit Test');
    expect($product->company_id)->toBe($this->company->id);
    expect($product->type)->toBe(ProductType::PRODUCT);
});

it('creates a service', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Service Test',
        'type' => 'service',
        'unit' => 'hour',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => false,
    ]);

    expect($product->type)->toBe(ProductType::SERVICE);
    expect($product->unit)->toBe('hour');
});

it('product belongs to current company', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->company_id)->toBe($this->company->id);
    expect($product->company->name)->toBe('Test Company');
});

it('rejects duplicate code within same company', function () {
    $service = new ProductService;

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'First Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $this->expectException(InvalidArgumentException::class);

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Duplicate',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);
});

it('validates product type', function () {
    $service = new ProductService;

    $this->expectException(ValueError::class);

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Test',
        'type' => 'invalid_type',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);
});

it('validates tax rate belongs to current company', function () {
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

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateTaxRate($otherTaxRate->id, $this->company->id);
});

it('rejects inactive tax rate', function () {
    $this->taxRate->update(['is_active' => false]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateTaxRate($this->taxRate->id, $this->company->id);
});

it('validates sales account belongs to current company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'FY2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $otherAccount = Account::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => '701000',
        'name' => 'Ventes',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateSalesAccount($otherAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('validates sales account belongs to current fiscal year', function () {
    $otherFy = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'FY2025',
        'code' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_active' => false,
    ]);
    $oldAccount = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => '701000',
        'name' => 'Ventes Old',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateSalesAccount($oldAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('rejects inactive sales account', function () {
    $this->account->update(['is_active' => false]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateSalesAccount($this->account->id, $this->company->id, $this->fiscalYear->id);
});

it('validates purchase account belongs to current company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);
    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'FY2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);
    $otherAccount = Account::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => '601000',
        'name' => 'Achats',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validatePurchaseAccount($otherAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('rejects inactive purchase account', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '601000',
        'name' => 'Achats',
        'account_type' => 'expense',
        'is_active' => false,
    ]);

    $service = new ProductService;

    $this->expectException(InvalidArgumentException::class);
    $service->validatePurchaseAccount($account->id, $this->company->id, $this->fiscalYear->id);
});

it('blocks viewer from mutating product', function () {
    $viewer = User::factory()->create();
    $viewer->email_verified_at = now();
    $viewer->save();

    $this->company->users()->attach($viewer, ['role' => 'viewer', 'is_active' => true]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Test',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($viewer->cannot('update', $product))->toBeTrue();
    expect($viewer->cannot('delete', $product))->toBeTrue();
    expect($viewer->can('view', $product))->toBeTrue();
});

it('admin can manage products', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Test',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($this->user->can('update', $product))->toBeTrue();
    expect($this->user->can('delete', $product))->toBeTrue();
    expect($this->user->can('view', $product))->toBeTrue();
    expect($this->user->can('activate', $product))->toBeTrue();
    expect($this->user->can('deactivate', $product))->toBeTrue();
});

it('blocks cross-company product access', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $product = Product::create([
        'company_id' => $otherCompany->id,
        'code' => 'PRD001',
        'name' => 'Other Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($this->user->can('view', $product))->toBeFalse();
    expect($this->user->can('update', $product))->toBeFalse();
    expect($this->user->can('delete', $product))->toBeFalse();
});

it('allows search by code and name', function () {
    $service = new ProductService;

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'ABC Produit',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD002',
        'name' => 'XYZ Service',
        'type' => 'service',
        'unit' => 'hour',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => false,
    ]);

    $searchByCode = Product::where('company_id', $this->company->id)
        ->where('code', 'like', '%PRD001%')
        ->pluck('name')
        ->toArray();
    expect($searchByCode)->toContain('ABC Produit');
    expect($searchByCode)->not->toContain('XYZ Service');

    $searchByName = Product::where('company_id', $this->company->id)
        ->where('name', 'like', '%XYZ%')
        ->pluck('name')
        ->toArray();
    expect($searchByName)->toContain('XYZ Service');
    expect($searchByName)->not->toContain('ABC Produit');
});

it('supports pagination', function () {
    $service = new ProductService;

    for ($i = 1; $i <= 20; $i++) {
        $service->createProduct([
            'company_id' => $this->company->id,
            'code' => 'PRD'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'name' => "Produit {$i}",
            'type' => 'product',
            'unit' => 'unit',
            'is_active' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
        ]);
    }

    expect(Product::where('company_id', $this->company->id)->count())->toBe(20);

    $page1 = Product::where('company_id', $this->company->id)
        ->orderBy('code')
        ->paginate(15);

    expect($page1->total())->toBe(20);
    expect($page1->count())->toBe(15);
});

it('activates and deactivates product', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Test Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->deactivate($product);
    $product->refresh();
    expect($product->is_active)->toBeFalse();

    $service->activate($product);
    $product->refresh();
    expect($product->is_active)->toBeTrue();
});

it('inactive product remains in database', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Test Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->deactivate($product);

    expect(Product::where('id', $product->id)->exists())->toBeTrue();
});

it('product show page respects company isolation', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $product = Product::create([
        'company_id' => $otherCompany->id,
        'code' => 'PRD001',
        'name' => 'Other Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($this->user->can('view', $product))->toBeFalse();
});

it('generates unique codes', function () {
    $service = new ProductService;

    $code1 = $service->generateCode($this->company->id);
    expect($code1)->toBe('PRD001');

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => $code1,
        'name' => 'Product 1',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $code2 = $service->generateCode($this->company->id);
    expect($code2)->toBe('PRD002');
});

it('generates codes independent per company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $service = new ProductService;

    $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Product 1',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $otherCode = $service->generateCode($otherCompany->id);
    expect($otherCode)->toBe('PRD001');
});

it('creates product with pricing', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Priced Product',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '10.500',
        'sale_price' => '25.000',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->purchase_price)->toBe('10.500');
    expect($product->sale_price)->toBe('25.000');
});

it('creates product with tax rate', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Taxed Product',
        'type' => 'product',
        'unit' => 'unit',
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->tax_rate_id)->toBe($this->taxRate->id);
    expect($product->taxRate->name)->toBe('TVA 19%');
});

it('creates product with sales account', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Accounted Product',
        'type' => 'product',
        'unit' => 'unit',
        'sales_account_id' => $this->account->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->sales_account_id)->toBe($this->account->id);
    expect($product->salesAccount->code)->toBe('701000');
});

it('creates product with purchase account', function () {
    $service = new ProductService;

    $purchaseAccount = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '601000',
        'name' => 'Achats de marchandises',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Purchased Product',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_account_id' => $purchaseAccount->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($product->purchase_account_id)->toBe($purchaseAccount->id);
    expect($product->purchaseAccount->code)->toBe('601000');
});

it('updates a product', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Old Name',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $updated = $service->updateProduct($product, [
        'code' => 'PRD001',
        'name' => 'New Name',
        'type' => 'service',
        'unit' => 'hour',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => false,
    ]);

    expect($updated->name)->toBe('New Name');
    expect($updated->type)->toBe(ProductType::SERVICE);
    expect($updated->unit)->toBe('hour');
});

it('updates product pricing', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Product',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $updated = $service->updateProduct($product, [
        'code' => 'PRD001',
        'name' => 'Product',
        'type' => 'product',
        'unit' => 'unit',
        'purchase_price' => '15.000',
        'sale_price' => '30.000',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    expect($updated->purchase_price)->toBe('15.000');
    expect($updated->sale_price)->toBe('30.000');
});

it('deletes a product', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'To Delete',
        'type' => 'product',
        'unit' => 'unit',
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->delete($product);

    expect(Product::where('id', $product->id)->exists())->toBeFalse();
});

it('deletes product with accounts set', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'To Delete With Accounts',
        'type' => 'product',
        'unit' => 'unit',
        'tax_rate_id' => $this->taxRate->id,
        'sales_account_id' => $this->account->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->delete($product);

    expect(Product::where('id', $product->id)->exists())->toBeFalse();
    expect(TaxRate::where('id', $this->taxRate->id)->exists())->toBeTrue();
    expect(Account::where('id', $this->account->id)->exists())->toBeTrue();
});

it('tax rate is preserved after product deletion', function () {
    $service = new ProductService;

    $product = $service->createProduct([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Product',
        'type' => 'product',
        'unit' => 'unit',
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $service->delete($product);

    expect(TaxRate::where('id', $this->taxRate->id)->exists())->toBeTrue();
});

it('existing accounting modules remain intact', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '521000',
        'name' => 'Banque',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    expect($account->code)->toBe('521000');
    expect($account->company_id)->toBe($this->company->id);

    $accounts = Account::where('company_id', $this->company->id)->get();
    expect($accounts->count())->toBeGreaterThanOrEqual(2);
});

it('validates required fields', function () {
    $service = new ProductService;

    $this->expectException(ErrorException::class);

    $service->createProduct([
        'company_id' => $this->company->id,
    ]);
});
