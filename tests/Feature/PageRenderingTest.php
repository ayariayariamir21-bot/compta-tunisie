<?php

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Routing\Route;

/**
 * Smoke test: every page of the application renders for an administrator,
 * as one HTML document (no layout rendered twice), with real records.
 */
it('renders every application page with a single layout', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $company = Company::create(['name' => 'Smoke SARL', 'currency' => 'TND', 'is_active' => true]);
    $company->users()->attach($user, ['role' => 'admin', 'is_active' => true]);

    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id, 'name' => 'Exercice 2026', 'code' => '2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
    ]);
    $period = AccountingPeriod::create([
        'fiscal_year_id' => $fiscalYear->id, 'name' => 'Janvier 2026', 'code' => '2026-01',
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_open' => true, 'is_closed' => false,
    ]);
    $account = Account::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fiscalYear->id, 'code' => '532',
        'name' => 'Banque', 'account_type' => 'asset', 'is_active' => true,
    ]);
    $journal = Journal::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fiscalYear->id, 'code' => 'BQ',
        'name' => 'Banque', 'type' => 'banque', 'is_active' => true,
    ]);
    $paymentMethod = PaymentMethod::create(['company_id' => $company->id, 'code' => 'VIR', 'name' => 'Virement', 'type' => 'bank_transfer', 'is_active' => true]);
    $taxRate = TaxRate::create(['company_id' => $company->id, 'code' => 'TVA19', 'name' => 'TVA 19%', 'rate' => '19.000', 'type' => 'vat', 'is_active' => true]);
    $customer = Customer::create(['company_id' => $company->id, 'code' => 'CL1', 'name' => 'Client', 'customer_type' => 'company', 'country' => 'TN', 'is_active' => true]);
    $supplier = Supplier::create(['company_id' => $company->id, 'code' => 'FR1', 'name' => 'Fournisseur', 'supplier_type' => 'company', 'country' => 'TN', 'is_active' => true]);
    $product = Product::create(['company_id' => $company->id, 'code' => 'P1', 'name' => 'Produit', 'type' => 'service', 'is_active' => true]);

    $context = ['company_id' => $company->id, 'fiscal_year_id' => $fiscalYear->id, 'accounting_period_id' => $period->id, 'journal_id' => $journal->id, 'created_by' => $user->id];
    $quote = Quote::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'quote_number' => 'DEV-1', 'quote_date' => '2026-01-10', 'currency' => 'TND', 'status' => 'draft', 'created_by' => $user->id]);
    $invoice = Invoice::create($context + ['customer_id' => $customer->id, 'invoice_number' => 'BR-SMOKE', 'invoice_date' => '2026-01-10', 'status' => 'draft']);
    $creditNote = CreditNote::create($context + ['customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'credit_note_number' => 'BR-AV', 'credit_note_date' => '2026-01-12', 'status' => 'draft']);
    $purchaseInvoice = PurchaseInvoice::create($context + ['supplier_id' => $supplier->id, 'invoice_number' => 'ACH-1', 'invoice_date' => '2026-01-10', 'status' => 'draft']);
    $expense = Expense::create($context + ['expense_number' => 'NDF-1', 'expense_date' => '2026-01-10', 'status' => 'draft']);
    $customerPayment = CustomerPayment::create($context + ['customer_id' => $customer->id, 'payment_method_id' => $paymentMethod->id, 'destination_account_id' => $account->id, 'payment_number' => 'ENC-1', 'payment_date' => '2026-01-15', 'amount' => '10.000', 'status' => 'draft']);
    $supplierPayment = SupplierPayment::create($context + ['supplier_id' => $supplier->id, 'payment_method_id' => $paymentMethod->id, 'destination_account_id' => $account->id, 'payment_number' => 'REG-1', 'payment_date' => '2026-01-15', 'amount' => '10.000', 'status' => 'draft']);
    $journalEntry = JournalEntry::create($context + ['entry_number' => 'BQ-2026-000001', 'entry_date' => '2026-01-15', 'status' => 'draft']);

    $parameters = [
        'accountId' => $account->id, 'companyId' => $company->id, 'creditNoteId' => $creditNote->id,
        'customerId' => $customer->id, 'expenseId' => $expense->id, 'fiscalYearId' => $fiscalYear->id,
        'invoiceId' => $invoice->id, 'journalEntryId' => $journalEntry->id, 'journalId' => $journal->id,
        'paymentMethodId' => $paymentMethod->id, 'productId' => $product->id, 'purchaseInvoiceId' => $purchaseInvoice->id,
        'quoteId' => $quote->id, 'supplierId' => $supplier->id, 'supplierPaymentId' => $supplierPayment->id,
        'taxRateId' => $taxRate->id, 'paymentId' => $customerPayment->id,
    ];

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with($route->getActionName(), 'App\\Livewire\\'));

    expect($routes)->not->toBeEmpty();

    $failures = [];
    foreach ($routes as $route) {
        $uri = '/'.ltrim(preg_replace_callback('/\{(\w+)\??\}/', fn (array $match): string => (string) ($parameters[$match[1]] ?? 'missing-'.$match[1]), $route->uri()), '/');

        $response = $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id, 'current_fiscal_year_id' => $fiscalYear->id])
            ->get($uri);

        $htmlDocuments = substr_count(strtolower((string) $response->getContent()), '<html');

        if ($response->status() !== 200 || $htmlDocuments !== 1) {
            $failures[] = $uri.' → '.$response->status().' ('.$htmlDocuments.' <html>) '.mb_substr((string) $response->exception?->getMessage(), 0, 200);
        }
    }

    expect($failures)->toBe([]);
});
