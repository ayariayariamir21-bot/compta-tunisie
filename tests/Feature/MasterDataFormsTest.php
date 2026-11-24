<?php

use App\Livewire\Accounts\Create;
use App\Livewire\Accounts\Edit;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use Livewire\Livewire;

/**
 * Every master-data form must save through the real Livewire component,
 * including its validation rules (enum fields in particular).
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::create(['name' => 'Formulaires SARL', 'currency' => 'TND', 'is_active' => true]);
    $this->company->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);
    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id, 'name' => 'Exercice 2026', 'code' => '2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
    ]);
    $this->actingAs($this->user);
    session(['current_company_id' => $this->company->id, 'current_fiscal_year_id' => $this->fiscalYear->id]);
});

dataset('master data forms', [
    'compte' => [Create::class, Edit::class, 'accountId', Account::class,
        ['code' => '6061', 'name' => 'Fournitures', 'account_type' => 'expense'], 'account_type', 'asset'],
    'client' => [App\Livewire\Customers\Create::class, App\Livewire\Customers\Edit::class, 'customerId', Customer::class,
        ['code' => 'CL900', 'name' => 'Client formulaire', 'customer_type' => 'company', 'country' => 'TN', 'payment_terms_days' => 30], 'customer_type', 'individual'],
    'fournisseur' => [App\Livewire\Suppliers\Create::class, App\Livewire\Suppliers\Edit::class, 'supplierId', Supplier::class,
        ['code' => 'FR900', 'name' => 'Fournisseur formulaire', 'supplier_type' => 'company', 'country' => 'TN', 'payment_terms_days' => 30], 'supplier_type', 'other'],
    'produit' => [App\Livewire\Products\Create::class, App\Livewire\Products\Edit::class, 'productId', Product::class,
        ['code' => 'PR900', 'name' => 'Prestation', 'type' => 'service', 'unit' => 'heure'], 'type', 'product'],
    'journal' => [App\Livewire\Journals\Create::class, App\Livewire\Journals\Edit::class, 'journalId', Journal::class,
        ['code' => 'VT2', 'name' => 'Ventes export', 'type' => 'ventes'], 'type', 'operations_diverses'],
    'moyen de paiement' => [App\Livewire\PaymentMethods\Create::class, App\Livewire\PaymentMethods\Edit::class, 'paymentMethodId', PaymentMethod::class,
        ['code' => 'TRT', 'name' => 'Traite', 'type' => 'other', 'sort_order' => 9], 'type', 'cheque'],
    'taux de taxe' => [App\Livewire\TaxRates\Create::class, App\Livewire\TaxRates\Edit::class, 'taxRateId', TaxRate::class,
        ['code' => 'TVA12', 'name' => 'TVA 12%', 'rate' => '12', 'type' => 'vat', 'sort_order' => 9], 'type', 'exempt'],
]);

it('creates then edits a record through its form', function (string $createComponent, string $editComponent, string $idParameter, string $modelClass, array $values, string $enumField, string $newEnumValue) {
    $create = Livewire::test($createComponent);
    foreach ($values as $field => $value) {
        $create->set($field, $value);
    }
    $create->call('store')->assertHasNoErrors();

    $record = $modelClass::where('code', $values['code'])->firstOrFail();
    expect($record->{$enumField}->value)->toBe($values[$enumField]);

    Livewire::test($editComponent, [$idParameter => $record->id])
        ->assertSet($enumField, $values[$enumField])
        ->set($enumField, $newEnumValue)
        ->call('update')
        ->assertHasNoErrors();

    expect($record->fresh()->{$enumField}->value)->toBe($newEnumValue);
})->with('master data forms');

it('rejects an unknown enum value with a validation error', function () {
    Livewire::test(App\Livewire\Customers\Create::class)
        ->set('code', 'CL901')
        ->set('name', 'Client')
        ->set('customer_type', 'martian')
        ->set('country', 'TN')
        ->set('payment_terms_days', 0)
        ->call('store')
        ->assertHasErrors(['customer_type']);
});
