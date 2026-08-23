<?php

use App\Livewire\Companies\Create;
use App\Livewire\Companies\Edit;
use App\Livewire\Companies\Index;
use App\Livewire\JournalEntries\Show;
use App\Livewire\Reports\BalanceSheet;
use App\Livewire\Reports\CustomerStatement;
use App\Livewire\Reports\GeneralLedger;
use App\Livewire\Reports\IncomeStatement;
use App\Livewire\Reports\SupplierStatement;
use App\Livewire\Reports\TrialBalance;
use App\Livewire\Reports\VatReport;
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

    Route::get('reports/balance-sheet', BalanceSheet::class)
        ->name('reports.balance-sheet');

    Route::get('reports/income-statement', IncomeStatement::class)
        ->name('reports.income-statement');

    Route::get('reports/vat', VatReport::class)
        ->name('reports.vat');

    Route::get('reports/customer-statement/{customerId?}', CustomerStatement::class)
        ->name('reports.customer-statement');

    Route::get('reports/supplier-statement/{supplierId?}', SupplierStatement::class)
        ->name('reports.supplier-statement');

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

    Route::get('quotes', App\Livewire\Quotes\Index::class)
        ->name('quotes.index');

    Route::get('quotes/create', App\Livewire\Quotes\Create::class)
        ->name('quotes.create');

    Route::get('quotes/{quoteId}', App\Livewire\Quotes\Show::class)
        ->name('quotes.show');

    Route::get('quotes/{quoteId}/edit', App\Livewire\Quotes\Edit::class)
        ->name('quotes.edit');

    Route::get('invoices', App\Livewire\Invoices\Index::class)
        ->name('invoices.index');

    Route::get('invoices/create', App\Livewire\Invoices\Create::class)
        ->name('invoices.create');

    Route::get('invoices/{invoiceId}', App\Livewire\Invoices\Show::class)
        ->name('invoices.show');

    Route::get('invoices/{invoiceId}/edit', App\Livewire\Invoices\Edit::class)
        ->name('invoices.edit');

    Route::get('credit-notes', App\Livewire\CreditNotes\Index::class)
        ->name('credit-notes.index');

    Route::get('credit-notes/create', App\Livewire\CreditNotes\Create::class)
        ->name('credit-notes.create');

    Route::get('credit-notes/{creditNoteId}', App\Livewire\CreditNotes\Show::class)
        ->name('credit-notes.show');

    Route::get('credit-notes/{creditNoteId}/edit', App\Livewire\CreditNotes\Edit::class)
        ->name('credit-notes.edit');

    Route::get('customer-payments', App\Livewire\CustomerPayments\Index::class)
        ->name('customer-payments.index');

    Route::get('customer-payments/create', App\Livewire\CustomerPayments\Create::class)
        ->name('customer-payments.create');

    Route::get('customer-payments/{paymentId}', App\Livewire\CustomerPayments\Show::class)
        ->name('customer-payments.show');

    Route::get('customer-payments/{paymentId}/edit', App\Livewire\CustomerPayments\Edit::class)
        ->name('customer-payments.edit');

    Route::get('purchase-invoices', App\Livewire\PurchaseInvoices\Index::class)
        ->name('purchase-invoices.index');

    Route::get('purchase-invoices/create', App\Livewire\PurchaseInvoices\Create::class)
        ->name('purchase-invoices.create');

    Route::get('purchase-invoices/{purchaseInvoiceId}', App\Livewire\PurchaseInvoices\Show::class)
        ->name('purchase-invoices.show');

    Route::get('purchase-invoices/{purchaseInvoiceId}/edit', App\Livewire\PurchaseInvoices\Edit::class)
        ->name('purchase-invoices.edit');

    Route::get('supplier-payments', App\Livewire\SupplierPayments\Index::class)
        ->name('supplier-payments.index');

    Route::get('supplier-payments/create', App\Livewire\SupplierPayments\Create::class)
        ->name('supplier-payments.create');

    Route::get('supplier-payments/{supplierPaymentId}', App\Livewire\SupplierPayments\Show::class)
        ->name('supplier-payments.show');

    Route::get('supplier-payments/{supplierPaymentId}/edit', App\Livewire\SupplierPayments\Edit::class)
        ->name('supplier-payments.edit');

    Route::get('expenses', App\Livewire\Expenses\Index::class)
        ->name('expenses.index');

    Route::get('expenses/create', App\Livewire\Expenses\Create::class)
        ->name('expenses.create');

    Route::get('expenses/{expenseId}', App\Livewire\Expenses\Show::class)
        ->name('expenses.show');

    Route::get('expenses/{expenseId}/edit', App\Livewire\Expenses\Edit::class)
        ->name('expenses.edit');
});

require __DIR__.'/settings.php';
