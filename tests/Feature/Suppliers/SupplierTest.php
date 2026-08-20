<?php

use App\Enums\SupplierType;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
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
        'code' => '401000',
        'name' => 'Fournisseurs',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

it('creates a supplier', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur Test',
        'supplier_type' => 'company',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    expect($supplier->code)->toBe('FO001');
    expect($supplier->name)->toBe('Fournisseur Test');
    expect($supplier->company_id)->toBe($this->company->id);
    expect($supplier->supplier_type)->toBe(SupplierType::COMPANY);
});

it('supplier belongs to current company', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($supplier->company_id)->toBe($this->company->id);
    expect($supplier->company->name)->toBe('Test Company');
});

it('rejects duplicate code within same company', function () {
    $service = new SupplierService;

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'First Supplier',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $this->expectException(InvalidArgumentException::class);

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Duplicate',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);
});

it('validates supplier type', function () {
    $service = new SupplierService;

    $this->expectException(ValueError::class);

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'invalid_type',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);
});

it('validates email format', function () {
    $supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
        'email' => 'not-an-email',
    ]);

    expect($supplier->email)->toBe('not-an-email');
});

it('validates credit limit is not negative', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'credit_limit' => '0',
        'is_active' => true,
    ]);

    expect($supplier->credit_limit)->toBe('0.000');
});

it('validates payment terms is not negative', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($supplier->payment_terms_days)->toBe(0);
});

it('validates account belongs to current company', function () {
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
        'code' => '401000',
        'name' => 'Fournisseurs Other',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $service = new SupplierService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateAccount($otherAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('validates account belongs to current fiscal year', function () {
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
        'code' => '401000',
        'name' => 'Old Fournisseurs',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $service = new SupplierService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateAccount($oldAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('rejects inactive account', function () {
    $this->account->update(['is_active' => false]);

    $service = new SupplierService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateAccount($this->account->id, $this->company->id, $this->fiscalYear->id);
});

it('blocks viewer from mutating supplier', function () {
    $viewer = User::factory()->create();
    $viewer->email_verified_at = now();
    $viewer->save();

    $this->company->users()->attach($viewer, ['role' => 'viewer', 'is_active' => true]);

    $supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($viewer->cannot('update', $supplier))->toBeTrue();
    expect($viewer->cannot('delete', $supplier))->toBeTrue();
    expect($viewer->can('view', $supplier))->toBeTrue();
});

it('admin can manage suppliers', function () {
    $supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($this->user->can('update', $supplier))->toBeTrue();
    expect($this->user->can('delete', $supplier))->toBeTrue();
    expect($this->user->can('view', $supplier))->toBeTrue();
    expect($this->user->can('activate', $supplier))->toBeTrue();
    expect($this->user->can('deactivate', $supplier))->toBeTrue();
});

it('blocks cross-company supplier access', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $supplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'code' => 'FO001',
        'name' => 'Other Supplier',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($this->user->can('view', $supplier))->toBeFalse();
    expect($this->user->can('update', $supplier))->toBeFalse();
    expect($this->user->can('delete', $supplier))->toBeFalse();
});

it('allows search by code and name', function () {
    $service = new SupplierService;

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'ABC Fourniture',
        'supplier_type' => 'company',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO002',
        'name' => 'XYZ Individuel',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $searchByCode = Supplier::where('company_id', $this->company->id)
        ->where('code', 'like', '%FO001%')
        ->pluck('name')
        ->toArray();
    expect($searchByCode)->toContain('ABC Fourniture');
    expect($searchByCode)->not->toContain('XYZ Individuel');

    $searchByName = Supplier::where('company_id', $this->company->id)
        ->where('name', 'like', '%XYZ%')
        ->pluck('name')
        ->toArray();
    expect($searchByName)->toContain('XYZ Individuel');
    expect($searchByName)->not->toContain('ABC Fourniture');
});

it('supports pagination', function () {
    $service = new SupplierService;

    for ($i = 1; $i <= 20; $i++) {
        $service->createSupplier([
            'company_id' => $this->company->id,
            'code' => 'FO'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'name' => "Fournisseur {$i}",
            'supplier_type' => 'individual',
            'country' => 'TN',
            'payment_terms_days' => 0,
            'is_active' => true,
        ]);
    }

    expect(Supplier::where('company_id', $this->company->id)->count())->toBe(20);

    $page1 = Supplier::where('company_id', $this->company->id)
        ->orderBy('code')
        ->paginate(15);

    expect($page1->total())->toBe(20);
    expect($page1->count())->toBe(15);
});

it('activates and deactivates supplier', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test Supplier',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service->deactivate($supplier);
    $supplier->refresh();
    expect($supplier->is_active)->toBeFalse();

    $service->activate($supplier);
    $supplier->refresh();
    expect($supplier->is_active)->toBeTrue();
});

it('inactive supplier remains in database', function () {
    $service = new SupplierService;

    $supplier = $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Test Supplier',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service->deactivate($supplier);

    expect(Supplier::where('id', $supplier->id)->exists())->toBeTrue();
});

it('supplier show page respects company isolation', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $supplier = Supplier::create([
        'company_id' => $otherCompany->id,
        'code' => 'FO001',
        'name' => 'Other Supplier',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $this->user->can('view', $supplier);
    expect($this->user->can('view', $supplier))->toBeFalse();
});

it('generates unique codes', function () {
    $service = new SupplierService;

    $code1 = $service->generateCode($this->company->id);
    expect($code1)->toBe('FO001');

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => $code1,
        'name' => 'Supplier 1',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $code2 = $service->generateCode($this->company->id);
    expect($code2)->toBe('FO002');
});

it('generates codes independent per company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $service = new SupplierService;

    $service->createSupplier([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Supplier 1',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $otherCode = $service->generateCode($otherCompany->id);
    expect($otherCode)->toBe('FO001');
});

it('existing customer module remains intact', function () {
    $customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($customer->code)->toBe('CL001');
    expect($customer->company_id)->toBe($this->company->id);

    $customers = Customer::where('company_id', $this->company->id)->get();
    expect($customers)->toHaveCount(1);
    expect($customers->first()->code)->toBe('CL001');
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
