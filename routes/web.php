<?php

use App\Livewire\Companies\Create;
use App\Livewire\Companies\Edit;
use App\Livewire\Companies\Index;
use App\Livewire\JournalEntries\Show;
use App\Livewire\Reports\GeneralLedger;
use App\Livewire\Reports\TrialBalance;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('companies', Index::class)
        ->name('companies.index');

    Route::get('companies/create', Create::class)
        ->name('companies.create');

    Route::get('companies/{companyId}/edit', Edit::class)
        ->name('companies.edit');

    Route::get('fiscal-years', App\Livewire\FiscalYears\Index::class)
        ->name('fiscal-years.index');

    Route::get('fiscal-years/create', App\Livewire\FiscalYears\Create::class)
        ->name('fiscal-years.create');

    Route::get('fiscal-years/{fiscalYearId}/edit', App\Livewire\FiscalYears\Edit::class)
        ->name('fiscal-years.edit');

    Route::get('accounting-periods', App\Livewire\AccountingPeriods\Index::class)
        ->name('accounting-periods.index');

    Route::get('accounts', App\Livewire\Accounts\Index::class)
        ->name('accounts.index');

    Route::get('accounts/create', App\Livewire\Accounts\Create::class)
        ->name('accounts.create');

    Route::get('accounts/{accountId}/edit', App\Livewire\Accounts\Edit::class)
        ->name('accounts.edit');

    Route::get('journals', App\Livewire\Journals\Index::class)
        ->name('journals.index');

    Route::get('journals/create', App\Livewire\Journals\Create::class)
        ->name('journals.create');

    Route::get('journals/{journalId}/edit', App\Livewire\Journals\Edit::class)
        ->name('journals.edit');

    Route::get('journal-entries', App\Livewire\JournalEntries\Index::class)
        ->name('journal-entries.index');

    Route::get('journal-entries/create', App\Livewire\JournalEntries\Create::class)
        ->name('journal-entries.create');

    Route::get('journal-entries/{journalEntryId}', Show::class)
        ->name('journal-entries.show');

    Route::get('journal-entries/{journalEntryId}/edit', App\Livewire\JournalEntries\Edit::class)
        ->name('journal-entries.edit');

    Route::get('payment-methods', App\Livewire\PaymentMethods\Index::class)
        ->name('payment-methods.index');

    Route::get('payment-methods/create', App\Livewire\PaymentMethods\Create::class)
        ->name('payment-methods.create');

    Route::get('payment-methods/{paymentMethodId}/edit', App\Livewire\PaymentMethods\Edit::class)
        ->name('payment-methods.edit');

    Route::get('tax-rates', App\Livewire\TaxRates\Index::class)
        ->name('tax-rates.index');

    Route::get('tax-rates/create', App\Livewire\TaxRates\Create::class)
        ->name('tax-rates.create');

    Route::get('tax-rates/{taxRateId}/edit', App\Livewire\TaxRates\Edit::class)
        ->name('tax-rates.edit');

    Route::get('reports/general-ledger', GeneralLedger::class)
        ->name('reports.general-ledger');

    Route::get('reports/trial-balance', TrialBalance::class)
        ->name('reports.trial-balance');

    Route::get('customers', App\Livewire\Customers\Index::class)
        ->name('customers.index');

    Route::get('customers/create', App\Livewire\Customers\Create::class)
        ->name('customers.create');

    Route::get('customers/{customerId}', App\Livewire\Customers\Show::class)
        ->name('customers.show');

    Route::get('customers/{customerId}/edit', App\Livewire\Customers\Edit::class)
        ->name('customers.edit');

    Route::get('suppliers', App\Livewire\Suppliers\Index::class)
        ->name('suppliers.index');

    Route::get('suppliers/create', App\Livewire\Suppliers\Create::class)
        ->name('suppliers.create');

    Route::get('suppliers/{supplierId}', App\Livewire\Suppliers\Show::class)
        ->name('suppliers.show');

    Route::get('suppliers/{supplierId}/edit', App\Livewire\Suppliers\Edit::class)
        ->name('suppliers.edit');

    Route::get('products', App\Livewire\Products\Index::class)
        ->name('products.index');

    Route::get('products/create', App\Livewire\Products\Create::class)
        ->name('products.create');

    Route::get('products/{productId}', App\Livewire\Products\Show::class)
        ->name('products.show');

    Route::get('products/{productId}/edit', App\Livewire\Products\Edit::class)
        ->name('products.edit');
});

require __DIR__.'/settings.php';
