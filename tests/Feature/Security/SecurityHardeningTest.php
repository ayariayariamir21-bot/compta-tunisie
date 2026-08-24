<?php

use App\Livewire\Companies\Edit as CompaniesEdit;
use App\Livewire\Companies\Members as CompaniesMembers;
use App\Livewire\Settings\Accounting as SettingsAccounting;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Security\ProcessOutcome;
use App\Services\Security\ProcessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = verifiedUser();
    $this->accountant = verifiedUser();
    $this->viewer = verifiedUser();
    $this->outsider = verifiedUser();

    $this->company = createCompany('Societe A');
    $this->otherCompany = createCompany('Societe B');

    $this->company->users()->attach($this->admin, ['role' => 'admin', 'is_active' => true]);
    $this->company->users()->attach($this->accountant, ['role' => 'accountant', 'is_active' => true]);
    $this->company->users()->attach($this->viewer, ['role' => 'viewer', 'is_active' => true]);

    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function verifiedUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function createCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'currency' => 'TND',
        'is_active' => true,
    ]);
}

/**
 * Minimal accounting scaffolding for the other company so cross-company
 * documents can be created through plain model writes. Idempotent per
 * company so repeated document creation stays cheap.
 *
 * @return array{fiscalYear: FiscalYear, period: AccountingPeriod, journal: Journal, method: PaymentMethod, account: Account}
 */
function createOtherCompanyAccounting(Company $company): array
{
    $fiscalYear = FiscalYear::firstOrCreate([
        'company_id' => $company->id,
        'code' => 'B2026',
    ], [
        'name' => 'Exercice B 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    $period = AccountingPeriod::firstOrCreate([
        'fiscal_year_id' => $fiscalYear->id,
        'code' => 'B2026-01',
    ], [
        'name' => 'Janvier B',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    $journal = Journal::firstOrCreate([
        'company_id' => $company->id,
        'code' => 'BQ',
    ], [
        'fiscal_year_id' => $fiscalYear->id,
        'name' => 'Journal Banque B',
        'type' => 'banque',
    ]);

    $method = PaymentMethod::firstOrCreate([
        'company_id' => $company->id,
        'code' => 'VIR',
    ], [
        'name' => 'Virement B',
        'type' => 'bank_transfer',
    ]);

    $account = Account::firstOrCreate([
        'company_id' => $company->id,
        'code' => '532000',
    ], [
        'fiscal_year_id' => $fiscalYear->id,
        'name' => 'Banque B',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    return compact('fiscalYear', 'period', 'journal', 'method', 'account');
}

/**
 * Create a draft document of the given kind inside the given company.
 *
 * @return Quote|Invoice|PurchaseInvoice|CustomerPayment|Expense
 */
function createForeignDraft(string $kind, Company $company): object
{
    $customer = Customer::create([
        'company_id' => $company->id,
        'code' => 'CL-'.strtoupper($company->id.$kind.'X'),
        'name' => "Client {$kind}",
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    $creator = User::factory()->create();

    if ($kind === 'quote') {
        return Quote::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'quote_number' => 'DEV-FOREIGN-001',
            'quote_date' => '2026-08-01',
            'created_by' => $creator->id,
        ]);
    }

    ['fiscalYear' => $fy, 'period' => $period, 'journal' => $journal] = createOtherCompanyAccounting($company);

    if ($kind === 'invoice') {
        return Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'fiscal_year_id' => $fy->id,
            'accounting_period_id' => $period->id,
            'journal_id' => $journal->id,
            'invoice_number' => 'FAC-FOREIGN-001',
            'invoice_date' => '2026-08-01',
            'created_by' => $creator->id,
        ]);
    }

    if ($kind === 'purchase-invoice') {
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'code' => 'FRS-FOREIGN',
            'name' => 'Fournisseur Etranger',
            'supplier_type' => 'individual',
            'country' => 'TN',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        return PurchaseInvoice::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fy->id,
            'accounting_period_id' => $period->id,
            'journal_id' => $journal->id,
            'invoice_number' => 'FAF-FOREIGN-001',
            'invoice_date' => '2026-08-01',
            'created_by' => $creator->id,
        ]);
    }

    if ($kind === 'customer-payment') {
        ['method' => $method, 'account' => $account] = createOtherCompanyAccounting($company);

        return CustomerPayment::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'fiscal_year_id' => $fy->id,
            'accounting_period_id' => $period->id,
            'payment_method_id' => $method->id,
            'journal_id' => $journal->id,
            'destination_account_id' => $account->id,
            'payment_number' => 'ENC-FOREIGN-001',
            'payment_date' => '2026-08-01',
            'amount' => '100.000',
            'created_by' => $creator->id,
        ]);
    }

    /** @var Expense $expense */
    $expense = Expense::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'accounting_period_id' => $period->id,
        'journal_id' => $journal->id,
        'expense_number' => 'DEP-FOREIGN-001',
        'expense_date' => '2026-08-01',
        'created_by' => $creator->id,
    ]);

    return $expense;
}

// ---------------------------------------------------------------------------
// HTTP security headers
// ---------------------------------------------------------------------------

it('applies baseline security headers to every response', function () {
    get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('applies CSP outside local and HSTS in production', function () {
    $this->app->detectEnvironment(fn (): string => 'staging');

    get(route('home'))
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->app->detectEnvironment(fn (): string => 'production');

    get(route('home'))
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

// ---------------------------------------------------------------------------
// Route exposure & role matrix
// ---------------------------------------------------------------------------

it('redirects guests away from sensitive routes', function () {
    foreach ([
        route('dashboard'),
        route('backups.index'),
        route('audit-logs.index'),
        route('customers.index'),
        route('invoices.index'),
        route('reports.trial-balance.pdf'),
    ] as $url) {
        get($url)->assertRedirect(route('login'));
    }
});

it('blocks viewers from administrative pages', function () {
    actingAs($this->viewer)
        ->get(route('companies.edit', ['companyId' => $this->company->id]))
        ->assertForbidden();

    actingAs($this->viewer)
        ->get(route('companies.members'))
        ->assertForbidden();
});

it('blocks accountants from member management pages', function () {
    actingAs($this->accountant)
        ->get(route('companies.members'))
        ->assertForbidden();
});

it('blocks accountants from saving accounting settings', function () {
    actingAs($this->accountant);

    Livewire::test(SettingsAccounting::class)
        ->call('save')
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Cross-company isolation (regressions for the hardening fixes)
// ---------------------------------------------------------------------------

it('prevents members of another company from viewing a quote', function () {
    $foreignQuote = createForeignDraft('quote', $this->otherCompany);

    // The quote is scoped to the current company before resolution, so the
    // request must not reveal (or render) another tenant's document: 404.
    actingAs($this->admin)
        ->get(route('quotes.show', ['quoteId' => $foreignQuote->id]))
        ->assertNotFound();
});

it('blocks membership-less users from reading tenant quotes', function () {
    $foreignQuote = createForeignDraft('quote', $this->otherCompany);

    actingAs($this->outsider)
        ->get(route('quotes.show', ['quoteId' => $foreignQuote->id]))
        ->assertForbidden();
});

it('blocks membership-less users from reading payment and expense details', function () {
    $payment = createForeignDraft('customer-payment', $this->otherCompany);
    $expense = createForeignDraft('expense', $this->otherCompany);

    actingAs($this->outsider)
        ->get(route('customer-payments.show', ['paymentId' => $payment->id]))
        ->assertForbidden();

    actingAs($this->outsider)
        ->get(route('expenses.show', ['expenseId' => $expense->id]))
        ->assertForbidden();
});

it('blocks cross-company editing of draft invoices', function () {
    $foreignInvoice = createForeignDraft('invoice', $this->otherCompany);

    actingAs($this->admin)
        ->get(route('invoices.edit', ['invoiceId' => $foreignInvoice->id]))
        ->assertForbidden();
});

it('blocks cross-company editing of draft quotes', function () {
    $foreignQuote = createForeignDraft('quote', $this->otherCompany);

    actingAs($this->admin)
        ->get(route('quotes.edit', ['quoteId' => $foreignQuote->id]))
        ->assertForbidden();
});

it('blocks cross-company editing of draft purchase invoices', function () {
    $foreignPurchase = createForeignDraft('purchase-invoice', $this->otherCompany);

    actingAs($this->admin)
        ->get(route('purchase-invoices.edit', ['purchaseInvoiceId' => $foreignPurchase->id]))
        ->assertForbidden();
});

it('still allows legitimate owners to edit their own drafts', function () {
    $ownInvoice = createForeignDraft('invoice', $this->company);

    session(['current_company_id' => $this->company->id]);

    actingAs($this->admin)
        ->get(route('invoices.edit', ['invoiceId' => $ownInvoice->id]))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Livewire action security
// ---------------------------------------------------------------------------

it('locks the company identity on the company edit component', function () {
    // The debug-only multiple-root check is unrelated here; the layout
    // wrapper legitimately renders siblings in debug mode.
    config(['app.debug' => false]);

    actingAs($this->admin);

    $component = Livewire::test(CompaniesEdit::class, ['companyId' => $this->company->id]);

    expect(fn () => $component->set('companyId', $this->otherCompany->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('re-authorizes company updates when the locked id points elsewhere', function () {
    actingAs($this->admin);

    // Simulate tampered component state: an admin of company A whose
    // hydrated companyId claims company B. The mutation must abort.
    $instance = new CompaniesEdit;
    $instance->companyId = $this->otherCompany->id;

    try {
        $ref = new ReflectionMethod(CompaniesEdit::class, 'authorizedCompany');
        $ref->setAccessible(true);

        $ref->invoke($instance);

        $this->fail('Expected authorization failure.');
    } catch (HttpException) {
        expect(true)->toBeTrue();
    }
});

it('locks the member roster company id', function () {
    actingAs($this->admin);

    $component = Livewire::test(CompaniesMembers::class);

    expect(fn () => $component->set('companyId', $this->otherCompany->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects member management calls from non-admins', function () {
    Livewire::actingAs($this->accountant);

    // mount() itself aborts for non-admins.
    Livewire::test(CompaniesMembers::class)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Authentication hardening
// ---------------------------------------------------------------------------

it('throttles repeated login failures', function () {
    foreach (range(1, 6) as $attempt) {
        $response = post(route('login.store'), [
            'email' => $this->admin->email,
            'password' => 'mot-de-passe-errone-'.$attempt,
        ]);
    }

    $response->assertTooManyRequests();
});

it('invalidates the session after logout', function () {
    actingAs($this->admin);

    post(route('logout'))->assertRedirect('/');

    get(route('dashboard'))->assertRedirect(route('login'));
});

it('regenerates the session identifier upon authentication', function () {
    get(route('login'))->assertOk();

    $oldSessionId = app('session.store')->getId();

    post(route('login.store'), [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect();

    $newSessionId = app('session.store')->getId();

    expect($newSessionId)->not->toBe($oldSessionId);
});

// ---------------------------------------------------------------------------
// Mass assignment
// ---------------------------------------------------------------------------

it('ignores privileged attributes supplied to registration', function () {
    post(route('register.store'), [
        'name' => 'Utilisateur Injection',
        'email' => 'injection@example.com',
        'password' => 'MotDePasse-Securise-1!',
        'password_confirmation' => 'MotDePasse-Securise-1!',
        'role' => 'admin',
        'company_id' => $this->company->id,
        'is_admin' => true,
    ])->assertRedirect();

    $user = User::query()->where('email', 'injection@example.com')->firstOrFail();

    expect($user->companies()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// PDF export exposure
// ---------------------------------------------------------------------------

it('requires authentication for pdf exports', function () {
    get(route('reports.trial-balance.pdf'))->assertRedirect(route('login'));
});

it('blocks cross-company statement exports', function () {
    $foreignCustomer = Customer::create([
        'company_id' => $this->otherCompany->id,
        'code' => 'CL-FRGN-STMT',
        'name' => 'Client Etranger Statement',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->get(route('reports.customer-statement.pdf', ['customerId' => $foreignCustomer->id]))
        ->assertNotFound();
});

it('rejects invalid report identifiers without server errors', function () {
    actingAs($this->admin)
        ->get(route('reports.general-ledger.pdf', ['account_id' => 'abc']))
        ->assertStatus(302);
});

// ---------------------------------------------------------------------------
// Backup operation throttling
// ---------------------------------------------------------------------------

it('throttles repeated expensive backup operations', function () {
    Storage::fake('local');

    $runner = new class implements ProcessRunner
    {
        public function run(array $command, array $env = [], ?int $timeout = null): ProcessOutcome
        {
            if (in_array('--version', $command, true)) {
                return new ProcessOutcome($command, true, 0, 'pg_dump (PostgreSQL) 18.0', '');
            }

            if (in_array('--format=custom', $command, true)) {
                $index = array_search('--file', $command, true);

                if ($index !== false && isset($command[$index + 1])) {
                    file_put_contents($command[$index + 1], random_bytes(64));
                }

                return new ProcessOutcome($command, true, 0, '', '');
            }

            return new ProcessOutcome($command, true, 0, '', '');
        }
    };

    $this->app->bind(ProcessRunner::class, fn (): ProcessRunner => $runner);

    config(['backups.allowed_environments' => ['testing']]);

    $lastResponse = null;

    foreach (range(1, 8) as $attempt) {
        $lastResponse = actingAs($this->admin)->post(route('backups.create'));

        if ($lastResponse->getStatusCode() === 429) {
            break;
        }
    }

    $lastResponse->assertTooManyRequests();
});
