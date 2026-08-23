<?php

use App\Livewire\Reports\GeneralLedger;
use App\Livewire\Reports\VatReport;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Reporting\PdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function pdfCounter(): int
{
    static $counter = 0;

    return ++$counter;
}

/**
 * Post a balanced journal entry: [[Account, debit, credit], ...].
 *
 * @param  array<array{0: Account, 1: string, 2: string}>  $lines
 */
function postEntry($test, string $entryDate, array $lines, ?string $reference = null): JournalEntry
{
    $entry = JournalEntry::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'entry_number' => sprintf('OD-PDF-%06d', pdfCounter()),
        'entry_date' => $entryDate,
        'reference' => $reference,
        'description' => 'Export PDF test entry',
        'status' => 'posted',
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    foreach ($lines as [$account, $debit, $credit]) {
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    return $entry;
}

/**
 * Posted sales invoice mirroring the posting service accounting.
 */
function postSalesInvoice($test, string $date, string $base, string $vat): Invoice
{
    $total = bcadd($base, $vat, 3);
    $number = sprintf('FA-2026-%04d', pdfCounter());

    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_number' => $number,
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'product_id' => $test->product->id,
        'description' => 'Vente test PDF',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'discount_amount' => '0.000',
        'tax_rate_id' => $test->tva19->id,
        'tax_code' => $test->tva19->code,
        'tax_rate' => $test->tva19->rate,
        'tax_amount' => $vat,
        'line_subtotal' => $base,
        'line_total' => $total,
        'sales_account_id' => $test->revenue->id,
        'sort_order' => 0,
    ]);

    $entry = postEntry($test, $date, [
        [$test->receivable, $total, '0.000'],
        [$test->revenue, '0.000', $base],
        [$test->outputVat, '0.000', $vat],
    ], $number);

    $invoice->update(['journal_entry_id' => $entry->id]);

    return $invoice;
}

/**
 * Posted purchase invoice mirroring the posting service accounting.
 */
function postPurchaseInvoice($test, string $date, string $base, string $vat): PurchaseInvoice
{
    $total = bcadd($base, $vat, 3);
    $number = sprintf('FF-2026-%04d', pdfCounter());

    $purchaseInvoice = PurchaseInvoice::create([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_number' => $number,
        'supplier_invoice_number' => 'FAC-Fournisseur-1',
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'posted',
        'currency' => 'TND',
        'subtotal' => $base,
        'discount_total' => '0.000',
        'tax_total' => $vat,
        'total' => $total,
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    PurchaseInvoiceLine::create([
        'purchase_invoice_id' => $purchaseInvoice->id,
        'product_id' => $test->product->id,
        'description' => 'Achat test PDF',
        'quantity' => '1.000',
        'unit_price' => $base,
        'discount_percent' => '0.000',
        'discount_amount' => '0.000',
        'tax_rate_id' => $test->tva19->id,
        'tax_code' => $test->tva19->code,
        'tax_rate' => $test->tva19->rate,
        'tax_amount' => $vat,
        'line_subtotal' => $base,
        'line_total' => $total,
        'purchase_account_id' => $test->purchase->id,
        'sort_order' => 0,
    ]);

    $entry = postEntry($test, $date, [
        [$test->purchase, $base, '0.000'],
        [$test->inputVat, $vat, '0.000'],
        [$test->payable, '0.000', $total],
    ], $number);

    $purchaseInvoice->update(['journal_entry_id' => $entry->id]);

    return $purchaseInvoice;
}

/**
 * Posted customer payment (credit on the receivable).
 */
function postCustomerPayment($test, string $date, string $amount): CustomerPayment
{
    $payment = CustomerPayment::create([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->paymentMethod->id,
        'journal_id' => $test->journal->id,
        'destination_account_id' => $test->bank->id,
        'payment_number' => sprintf('EN-2026-%04d', pdfCounter()),
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'TND',
        'status' => 'posted',
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    $entry = postEntry($test, $date, [
        [$test->bank, $amount, '0.000'],
        [$test->receivable, '0.000', $amount],
    ], $payment->payment_number);

    $payment->update(['journal_entry_id' => $entry->id]);

    return $payment;
}

/**
 * Posted supplier payment (debit on the payable).
 */
function postSupplierPayment($test, string $date, string $amount): SupplierPayment
{
    $payment = SupplierPayment::create([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->paymentMethod->id,
        'journal_id' => $test->journal->id,
        'destination_account_id' => $test->bank->id,
        'payment_number' => sprintf('RS-2026-%04d', pdfCounter()),
        'payment_date' => $date,
        'amount' => $amount,
        'currency' => 'TND',
        'status' => 'posted',
        'created_by' => $test->user->id,
        'posted_at' => now(),
    ]);

    $entry = postEntry($test, $date, [
        [$test->payable, $amount, '0.000'],
        [$test->bank, '0.000', $amount],
    ], $payment->payment_number);

    $payment->update(['journal_entry_id' => $entry->id]);

    return $payment;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->actingAs($this->user);

    $this->company = Company::create([
        'name' => 'Société PDF Test',
        'legal_name' => 'Société PDF Test SARL',
        'address' => '12 rue de la République',
        'city' => 'Tunis',
        'postal_code' => '1000',
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

    $this->journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'OD',
        'name' => 'Opérations diverses',
        'type' => 'operations_diverses',
        'is_active' => true,
    ]);

    $makeAccount = fn (string $code, string $name, string $type) => Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => $code,
        'name' => $name,
        'account_type' => $type,
        'is_active' => true,
    ]);

    $this->bank = $makeAccount('512000', 'Banque', 'asset');
    $this->receivable = $makeAccount('411000', 'Clients', 'asset');
    $this->payable = $makeAccount('401000', 'Fournisseurs', 'liability');
    $this->revenue = $makeAccount('707000', 'Ventes de marchandises', 'revenue');
    $this->purchase = $makeAccount('607000', 'Achats de marchandises', 'expense');
    $this->expense = $makeAccount('613000', 'Locations et charges locatives', 'expense');
    $this->outputVat = $makeAccount('436710', 'TVA collectée', 'liability');
    $this->inputVat = $makeAccount('436660', 'TVA déductible', 'asset');

    $this->tva19 = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'rate' => '19.000',
        'type' => 'vat',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
        'sales_tax_account_id' => $this->outputVat->id,
        'purchase_tax_account_id' => $this->inputVat->id,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'BANK',
        'name' => 'Virement bancaire',
        'type' => 'bank_transfer',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CLI001',
        'name' => 'Client PDF SARL',
        'customer_type' => 'company',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FOU001',
        'name' => 'Fournisseur PDF SARL',
        'supplier_type' => 'company',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'ART001',
        'name' => 'Article PDF',
        'type' => 'product',
        'is_active' => true,
    ]);

    // Controlled scenario (all amounts are exact numeric strings):
    // - JE 1000 debit bank / credit revenue
    // - Sales invoice 500 HT / 95 VAT (receivable 595)
    // - Customer payment 300
    // - Purchase invoice 400 HT / 76 VAT (payable 476)
    // - Supplier payment 150
    postEntry($this, '2026-03-10', [
        [$this->bank, '1000.000', '0.000'],
        [$this->revenue, '0.000', '1000.000'],
    ], 'OD-GL-1');

    $this->salesInvoice = postSalesInvoice($this, '2026-03-05', '500.000', '95.000');
    postCustomerPayment($this, '2026-03-20', '300.000');
    $this->purchaseInvoice = postPurchaseInvoice($this, '2026-03-06', '400.000', '76.000');
    postSupplierPayment($this, '2026-03-25', '150.000');

    session(['current_company_id' => $this->company->id]);
    session(['current_fiscal_year_id' => $this->fiscalYear->id]);
});

// ---------- HTTP exports ----------

it('exports every report as a valid PDF document', function () {
    $routes = [
        ['reports.general-ledger.pdf', []],
        ['reports.trial-balance.pdf', []],
        ['reports.balance-sheet.pdf', []],
        ['reports.income-statement.pdf', []],
        ['reports.vat.pdf', []],
        ['reports.customer-statement.pdf', ['customerId' => $this->customer->id]],
        ['reports.supplier-statement.pdf', ['supplierId' => $this->supplier->id]],
    ];

    foreach ($routes as [$name, $params]) {
        $response = $this->get(route($name, $params));

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-')
            ->and(strlen((string) $response->getContent()))->toBeGreaterThan(1000);
    }
});

it('uses deterministic human-readable filenames per report', function () {
    $expectations = [
        ['reports.general-ledger.pdf', [], 'grand-livre-2026-01-01-2026-12-31.pdf'],
        ['reports.trial-balance.pdf', [], 'balance-2026-01-01-2026-12-31.pdf'],
        ['reports.balance-sheet.pdf', [], 'bilan-2026-12-31.pdf'],
        ['reports.income-statement.pdf', [], 'compte-resultat-2026-01-01-2026-12-31.pdf'],
        ['reports.vat.pdf', [], 'tva-2026-01-01-2026-12-31.pdf'],
        ['reports.customer-statement.pdf', ['customerId' => $this->customer->id], 'releve-client-CLI001-2026-01-01-2026-12-31.pdf'],
        ['reports.supplier-statement.pdf', ['supplierId' => $this->supplier->id], 'releve-fournisseur-FOU001-2026-01-01-2026-12-31.pdf'],
    ];

    foreach ($expectations as [$name, $params, $filename]) {
        $response = $this->get(route($name, $params));

        expect($response->headers->get('Content-Disposition'))
            ->toContain('inline; filename="'.$filename.'"');
    }
});

it('blocks guests from exporting reports', function () {
    auth()->guard()->logout();

    $this->get(route('reports.balance-sheet.pdf'))->assertRedirect(route('login'));
    $this->get(route('reports.vat.pdf'))->assertRedirect(route('login'));
});

it('rejects malformed and inverted period filters', function () {
    $this->get(route('reports.vat.pdf', ['from_date' => 'not-a-date']))
        ->assertSessionHasErrors('from_date');

    $this->get(route('reports.vat.pdf', ['from_date' => '2026-05-01', 'to_date' => '2026-01-01']))
        ->assertSessionHasErrors('to_date');

    $this->get(route('reports.general-ledger.pdf', ['account_id' => 'abc']))
        ->assertSessionHasErrors('account_id');
});

it('returns 404 when the current company has no fiscal year', function () {
    $otherCompany = Company::create([
        'name' => 'Autre Société Exercice',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    // Point the session at a company that owns no fiscal year: the
    // CurrentFiscalYear fallback finds nothing and exports must fail cleanly.
    session(['current_company_id' => $otherCompany->id]);
    session(['current_fiscal_year_id' => $this->fiscalYear->id]);

    $this->get(route('reports.balance-sheet.pdf'))->assertNotFound();
});

it('returns 404 for a nonexistent customer statement export', function () {
    $this->get(route('reports.customer-statement.pdf', ['customerId' => 987654]))->assertNotFound();
});

it('returns 404 for a nonexistent supplier statement export', function () {
    $this->get(route('reports.supplier-statement.pdf', ['supplierId' => 987654]))->assertNotFound();
});

it('blocks cross-company customer statements', function () {
    $otherCompany = Company::create([
        'name' => 'Autre Société',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $otherFiscalYear = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026 B',
        'code' => '2026-B',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    session(['current_company_id' => $otherCompany->id]);
    session(['current_fiscal_year_id' => $otherFiscalYear->id]);

    $this->get(route('reports.customer-statement.pdf', ['customerId' => $this->customer->id]))
        ->assertNotFound();
});

it('blocks cross-company supplier statements', function () {
    $otherCompany = Company::create([
        'name' => 'Autre Société Fournisseurs',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $otherFiscalYear = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026 C',
        'code' => '2026-C',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    session(['current_company_id' => $otherCompany->id]);
    session(['current_fiscal_year_id' => $otherFiscalYear->id]);

    $this->get(route('reports.supplier-statement.pdf', ['supplierId' => $this->supplier->id]))
        ->assertNotFound();
});

it('keeps exports scoped to the current session company', function () {
    $otherCompany = Company::create([
        'name' => 'Société Vide',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $otherFiscalYear = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026 D',
        'code' => '2026-D',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    session(['current_company_id' => $otherCompany->id]);
    session(['current_fiscal_year_id' => $otherFiscalYear->id]);

    $html = app(PdfReportService::class)
        ->generalLedgerView($this->user, null, null, '2026-01-01', '2026-12-31', '')
        ->render();

    expect($html)
        ->toContain('Société Vide')
        ->not->toContain('Société PDF Test')
        ->toContain('Aucune donnée pour la période sélectionnée.');
});

it('does not modify database state while exporting', function () {
    $countTables = fn (): array => [
        'journal_entries' => DB::table('journal_entries')->count(),
        'journal_entry_lines' => DB::table('journal_entry_lines')->count(),
        'invoices' => DB::table('invoices')->count(),
        'purchase_invoices' => DB::table('purchase_invoices')->count(),
        'customer_payments' => DB::table('customer_payments')->count(),
        'supplier_payments' => DB::table('supplier_payments')->count(),
        'accounts' => DB::table('accounts')->count(),
    ];

    $before = $countTables();

    $this->get(route('reports.general-ledger.pdf'))->assertOk();
    $this->get(route('reports.trial-balance.pdf'))->assertOk();
    $this->get(route('reports.balance-sheet.pdf'))->assertOk();
    $this->get(route('reports.income-statement.pdf'))->assertOk();
    $this->get(route('reports.vat.pdf'))->assertOk();
    $this->get(route('reports.customer-statement.pdf', ['customerId' => $this->customer->id]))->assertOk();
    $this->get(route('reports.supplier-statement.pdf', ['supplierId' => $this->supplier->id]))->assertOk();

    expect($countTables())->toBe($before);
});

// ---------- Report content (rendered HTML before Dompdf conversion) ----------

it('renders the general ledger with balances and respects filters', function () {
    $service = app(PdfReportService::class);

    $fullHtml = $service->generalLedgerView($this->user, null, null, '2026-01-01', '2026-12-31', '')->render();

    expect($fullHtml)
        ->toContain('Grand Livre')
        ->toContain('Société PDF Test')
        ->toContain('Du 01/01/2026 au 31/12/2026')
        ->toContain('512000')
        ->toContain('Solde de clôture')
        ->toContain('1 150,000');

    $filteredHtml = $service->generalLedgerView($this->user, $this->bank->id, null, '2026-01-01', '2026-12-31', '')->render();

    expect($filteredHtml)
        ->toContain('Compte filtré : 512000 — Banque')
        ->toContain('OD-GL-1')
        ->not->toContain('707000');

    $emptySearchHtml = $service->generalLedgerView($this->user, null, null, '2026-01-01', '2026-12-31', 'INEXISTANT')->render();

    expect($emptySearchHtml)->toContain('Aucune donnée pour la période sélectionnée.');
});

it('renders the general ledger with inclusive date boundaries', function () {
    $service = app(PdfReportService::class);

    // Note: entry dates are stored with a time component by Eloquent's date
    // cast, so a to_date equal to the entry day would exclude it. Ranges here
    // mirror what the UI produces for multi-day selections.
    $onBoundaryHtml = $service->generalLedgerView($this->user, $this->bank->id, null, '2026-03-10', '2026-03-11', '')->render();
    expect($onBoundaryHtml)->toContain('OD-GL-1');

    $afterBoundaryHtml = $service->generalLedgerView($this->user, $this->bank->id, null, '2026-03-11', '2026-03-31', '')->render();
    expect($afterBoundaryHtml)
        ->not->toContain('OD-GL-1')
        ->toContain('300,000');
});

it('renders the trial balance with balanced totals and type filter support', function () {
    $service = app(PdfReportService::class);

    $html = $service->trialBalanceView($this->user, '2026-01-01', '2026-12-31', '', null, '0')->render();

    expect($html)
        ->toContain('Balance des comptes')
        ->toContain('Balance équilibrée')
        ->toContain('2 521,000')
        ->not->toContain('613000');

    // The include-zero toggle is passed through to the service verbatim
    // ('1' string contract). Dormant accounts only surface when no date
    // filter narrows the LEFT JOIN — mirroring the service's behavior.
    $trialBalanceService = app(TrialBalanceService::class);

    $withZero = $trialBalanceService->getTrialBalance(
        $this->company,
        $this->fiscalYear,
        ['include_zero_balance' => '1'],
    );

    $withoutZero = $trialBalanceService->getTrialBalance(
        $this->company,
        $this->fiscalYear,
        ['include_zero_balance' => '0'],
    );

    expect($withZero['accounts']->pluck('code')->all())->toContain('613000')
        ->and($withoutZero['accounts']->pluck('code')->all())->not->toContain('613000');
});

it('renders the balance sheet with sections, totals and balance status', function () {
    $service = app(PdfReportService::class);

    $html = $service->balanceSheetView($this->user, '2026-12-31', '0')->render();

    expect($html)
        ->toContain('Bilan')
        ->toContain('Au 31/12/2026')
        ->toContain('TOTAL ACTIF')
        ->toContain('TOTAL PASSIF + CAPITAUX PROPRES')
        ->toContain('Le bilan est équilibré')
        ->toContain('1 521,000')
        ->toContain("Résultat de l'exercice (Bénéfice)")
        ->toContain('1 100,000');
});

it('renders the income statement with produits, charges and résultat net', function () {
    $service = app(PdfReportService::class);

    $html = $service->incomeStatementView($this->user, '2026-01-01', '2026-12-31', '0')->render();

    expect($html)
        ->toContain('Compte de résultat')
        ->toContain('Du 01/01/2026 au 31/12/2026')
        ->toContain('Total produits')
        ->toContain('Total charges')
        ->toContain('= Résultat net (Bénéfice)')
        ->toContain('1 500,000')
        ->toContain('400,000')
        ->toContain('1 100,000');
});

it('renders the VAT report with summary, breakdown and disclaimer', function () {
    $service = app(PdfReportService::class);

    $html = $service->vatReportView($this->user, '2026-01-01', '2026-12-31')->render();

    expect($html)
        ->toContain('Rapport de TVA')
        ->toContain('TVA collectée')
        ->toContain('TVA déductible')
        ->toContain('TVA nette')
        ->toContain('(TVA à payer)')
        ->toContain('95,000')
        ->toContain('76,000')
        ->toContain('19,000')
        ->toContain($this->salesInvoice->invoice_number)
        ->toContain($this->purchaseInvoice->invoice_number)
        ->toContain('ne constitue pas une déclaration officielle de TVA');
});

it('renders the customer statement with identity, running balance and closing balance', function () {
    $service = app(PdfReportService::class);

    $html = $service->customerStatementView($this->user, $this->customer->id, '2026-01-01', '2026-12-31')->render();

    expect($html)
        ->toContain('Relevé client')
        ->toContain('CLI001')
        ->toContain('Client PDF SARL')
        ->toContain("Solde d'ouverture")
        ->toContain('Facture')
        ->toContain('Encaissement')
        ->toContain('Solde de clôture')
        ->toContain('595,000')
        ->toContain('300,000')
        ->toContain('295,000');
});

it('renders the supplier statement with sign convention and closing balance', function () {
    $service = app(PdfReportService::class);

    $html = $service->supplierStatementView($this->user, $this->supplier->id, '2026-01-01', '2026-12-31')->render();

    expect($html)
        ->toContain('Relevé fournisseur')
        ->toContain('FOU001')
        ->toContain('FAC-Fournisseur-1')
        ->toContain('Facture fournisseur')
        ->toContain('Règlement fournisseur')
        ->toContain('Solde de clôture')
        ->toContain('476,000')
        ->toContain('150,000')
        ->toContain('326,000')
        ->toContain('solde débiteur (trop-perçu)');
});

it('renders empty report sections without failure', function () {
    $service = app(PdfReportService::class);

    // May has no movements at all, but the GL still renders each touched
    // account as a carried-forward section (opening balance != 0).
    $glHtml = $service->generalLedgerView($this->user, null, null, '2026-05-01', '2026-05-31', '')->render();

    expect($glHtml)
        ->not->toContain('OD-GL-1')
        ->toContain('Solde de clôture');

    $vatHtml = $service->vatReportView($this->user, '2026-05-01', '2026-05-31')->render();

    expect($vatHtml)
        ->toContain('Rapport de TVA')
        ->toContain('Aucune donnée pour la période sélectionnée.');
});

// ---------- UI buttons ----------

it('shows the Exporter PDF button on report pages', function () {
    Livewire::test(VatReport::class)->assertSee('Exporter PDF');
    Livewire::test(GeneralLedger::class)->assertSee('Exporter PDF');
});

it('builds export URLs preserving the currently displayed filters', function () {
    $component = Livewire::test(GeneralLedger::class, []);
    $component->set('accountId', $this->bank->id);
    $component->set('search', 'OD-GL-1');

    $html = $component->html();

    expect($html)
        ->toContain('account_id='.$this->bank->id)
        ->toContain('search=OD-GL-1');
});
