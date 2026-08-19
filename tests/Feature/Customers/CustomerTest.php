<?php

use App\Enums\CustomerType;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\CustomerService;
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
        'code' => '411000',
        'name' => 'Clients',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

it('creates a customer', function () {
    $service = new CustomerService;

    $customer = $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'company',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    expect($customer->code)->toBe('CL001');
    expect($customer->name)->toBe('Client Test');
    expect($customer->company_id)->toBe($this->company->id);
    expect($customer->customer_type)->toBe(CustomerType::COMPANY);
});

it('customer belongs to current company', function () {
    $service = new CustomerService;

    $customer = $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($customer->company_id)->toBe($this->company->id);
    expect($customer->company->name)->toBe('Test Company');
});

it('rejects duplicate code within same company', function () {
    $service = new CustomerService;

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'First Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $this->expectException(InvalidArgumentException::class);

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Duplicate',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);
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
        'code' => '411000',
        'name' => 'Clients Other',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $service = new CustomerService;

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
        'code' => '411000',
        'name' => 'Old Clients',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $service = new CustomerService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateAccount($oldAccount->id, $this->company->id, $this->fiscalYear->id);
});

it('rejects inactive account', function () {
    $this->account->update(['is_active' => false]);

    $service = new CustomerService;

    $this->expectException(InvalidArgumentException::class);
    $service->validateAccount($this->account->id, $this->company->id, $this->fiscalYear->id);
});

it('blocks viewer from mutating customer', function () {
    $viewer = User::factory()->create();
    $viewer->email_verified_at = now();
    $viewer->save();

    $this->company->users()->attach($viewer, ['role' => 'viewer', 'is_active' => true]);

    $customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($viewer->cannot('update', $customer))->toBeTrue();
    expect($viewer->cannot('delete', $customer))->toBeTrue();
    expect($viewer->can('view', $customer))->toBeTrue();
});

it('admin can manage customers', function () {
    $customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($this->user->can('update', $customer))->toBeTrue();
    expect($this->user->can('delete', $customer))->toBeTrue();
    expect($this->user->can('view', $customer))->toBeTrue();
    expect($this->user->can('activate', $customer))->toBeTrue();
    expect($this->user->can('deactivate', $customer))->toBeTrue();
});

it('blocks cross-company customer access', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $customer = Customer::create([
        'company_id' => $otherCompany->id,
        'code' => 'CL001',
        'name' => 'Other Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    expect($this->user->can('view', $customer))->toBeFalse();
    expect($this->user->can('update', $customer))->toBeFalse();
    expect($this->user->can('delete', $customer))->toBeFalse();
});

it('allows search by code and name', function () {
    $service = new CustomerService;

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'ABC Company',
        'customer_type' => 'company',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL002',
        'name' => 'XYZ Individual',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $searchByCode = Customer::where('company_id', $this->company->id)
        ->where('code', 'like', '%CL001%')
        ->pluck('name')
        ->toArray();
    expect($searchByCode)->toContain('ABC Company');
    expect($searchByCode)->not->toContain('XYZ Individual');

    $searchByName = Customer::where('company_id', $this->company->id)
        ->where('name', 'like', '%XYZ%')
        ->pluck('name')
        ->toArray();
    expect($searchByName)->toContain('XYZ Individual');
    expect($searchByName)->not->toContain('ABC Company');
});

it('supports pagination', function () {
    $service = new CustomerService;

    for ($i = 1; $i <= 20; $i++) {
        $service->createCustomer([
            'company_id' => $this->company->id,
            'code' => 'CL'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'name' => "Client {$i}",
            'customer_type' => 'individual',
            'country' => 'TN',
            'payment_terms_days' => 0,
            'is_active' => true,
        ]);
    }

    expect(Customer::where('company_id', $this->company->id)->count())->toBe(20);

    $page1 = Customer::where('company_id', $this->company->id)
        ->orderBy('code')
        ->paginate(15);

    expect($page1->total())->toBe(20);
    expect($page1->count())->toBe(15);
});

it('activates and deactivates customer', function () {
    $service = new CustomerService;

    $customer = $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Test Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service->deactivate($customer);
    $customer->refresh();
    expect($customer->is_active)->toBeFalse();

    $service->activate($customer);
    $customer->refresh();
    expect($customer->is_active)->toBeTrue();
});

it('inactive customer remains in database', function () {
    $service = new CustomerService;

    $customer = $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Test Client',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $service->deactivate($customer);

    expect(Customer::where('id', $customer->id)->exists())->toBeTrue();
});

it('generates unique codes', function () {
    $service = new CustomerService;

    $code1 = $service->generateCode($this->company->id);
    expect($code1)->toBe('CL001');

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => $code1,
        'name' => 'Client 1',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $code2 = $service->generateCode($this->company->id);
    expect($code2)->toBe('CL002');
});

it('generates codes independent per company', function () {
    $otherCompany = Company::create(['name' => 'Other', 'currency' => 'TND', 'is_active' => true]);

    $service = new CustomerService;

    $service->createCustomer([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client 1',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);

    $otherCode = $service->generateCode($otherCompany->id);
    expect($otherCode)->toBe('CL001');
});
