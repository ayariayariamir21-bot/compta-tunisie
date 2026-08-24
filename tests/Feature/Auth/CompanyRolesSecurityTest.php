<?php

use App\Enums\CompanyRole;
use App\Enums\CreditNoteStatus;
use App\Enums\CustomerPaymentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\SupplierPaymentStatus;
use App\Livewire\Companies\Members;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\CompanyAccountingSetting;
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
use App\Services\CompanyMembershipService;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CompanyMembershipService;

    $this->owner = User::factory()->create();
    $this->owner->email_verified_at = now();
    $this->owner->save();

    $this->company = Company::create([
        'name' => 'Societe Test',
        'currency' => 'TND',
        'is_active' => true,
    ]);

    $this->company->users()->attach($this->owner, ['role' => 'admin', 'is_active' => true]);

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

function attachMember(Company $company, string $role, bool $isActive = true): User
{
    $user = User::factory()->create();
    $user->email_verified_at = now();
    $user->save();

    $company->users()->attach($user, ['role' => $role, 'is_active' => $isActive]);

    return $user;
}

// ---------- User role helpers ----------

it('exposes role helpers scoped to active memberships', function () {
    $admin = $this->owner;
    $accountant = attachMember($this->company, 'accountant');
    $viewer = attachMember($this->company, 'viewer');
    $inactiveAdmin = attachMember($this->company, 'admin', isActive: false);

    expect($admin->hasCompanyRole($this->company, CompanyRole::Admin))->toBeTrue()
        ->and($admin->isCompanyAdmin($this->company))->toBeTrue()
        ->and($accountant->hasCompanyRole($this->company, CompanyRole::Accountant))->toBeTrue()
        ->and($accountant->isCompanyAdmin($this->company))->toBeFalse()
        ->and($viewer->isActiveCompanyMember($this->company))->toBeTrue()
        ->and($viewer->isCompanyAdmin($this->company->id))->toBeFalse()
        ->and($inactiveAdmin->isActiveCompanyMember($this->company))->toBeFalse()
        ->and($inactiveAdmin->isCompanyAdmin($this->company))->toBeFalse();
});

it('keeps legacy generic members read-only', function () {
    $member = attachMember($this->company, 'member');
    $customer = new Customer(['company_id' => $this->company->id]);

    expect($member->can('view', $customer))->toBeTrue()
        ->and($member->can('create', [Customer::class, $this->company]))->toBeFalse();
});

// ---------- Business operations: admin / accountant / viewer ----------

it('lets admins and accountants operate on tiers while viewers stay read-only', function () {
    $accountant = attachMember($this->company, 'accountant');
    $viewer = attachMember($this->company, 'viewer');

    foreach ([Customer::class, Supplier::class, Product::class] as $model) {
        $record = new $model(['company_id' => $this->company->id]);

        foreach (['update', 'delete'] as $ability) {
            expect($this->owner->can($ability, $record))->toBeTrue()
                ->and($accountant->can($ability, $record))->toBeTrue()
                ->and($viewer->can($ability, $record))->toBeFalse();
        }

        expect($this->owner->can('create', [$model, $this->company]))->toBeTrue()
            ->and($accountant->can('create', [$model, $this->company]))->toBeTrue()
            ->and($viewer->can('create', [$model, $this->company]))->toBeFalse()
            ->and($viewer->can('view', $record))->toBeTrue()
            ->and($viewer->can('viewAny', $model))->toBeTrue();
    }
});

it('enforces quote workflow abilities by role', function () {
    $accountant = attachMember($this->company, 'accountant');
    $viewer = attachMember($this->company, 'viewer');

    $quote = new Quote([
        'company_id' => $this->company->id,
        'status' => QuoteStatus::DRAFT,
    ]);

    expect($accountant->can('update', $quote))->toBeTrue()
        ->and($accountant->can('send', $quote))->toBeTrue()
        ->and($accountant->can('delete', $quote))->toBeTrue()
        ->and($viewer->can('update', $quote))->toBeFalse()
        ->and($viewer->can('send', $quote))->toBeFalse()
        ->and($viewer->can('accept', $quote))->toBeFalse()
        ->and($viewer->can('reject', $quote))->toBeFalse()
        ->and($viewer->can('cancel', $quote))->toBeFalse()
        ->and($viewer->can('duplicate', $quote))->toBeFalse()
        ->and($viewer->can('view', $quote))->toBeTrue();
});

it('keeps document and payment mutations restricted to admins and accountants', function () {
    $accountant = attachMember($this->company, 'accountant');
    $legacyMember = attachMember($this->company, 'member');

    $cases = [
        [Invoice::class, ['status' => InvoiceStatus::DRAFT], ['post', 'cancel']],
        [CreditNote::class, ['status' => CreditNoteStatus::DRAFT], ['post', 'cancel']],
        [CustomerPayment::class, ['status' => CustomerPaymentStatus::DRAFT], ['post', 'cancel']],
        [PurchaseInvoice::class, ['status' => PurchaseInvoiceStatus::DRAFT], ['post', 'cancel']],
        [SupplierPayment::class, ['status' => SupplierPaymentStatus::DRAFT], ['post', 'cancel']],
        [Expense::class, ['status' => ExpenseStatus::DRAFT], ['post', 'cancel']],
        [JournalEntry::class, ['status' => JournalEntryStatus::DRAFT], []],
    ];

    foreach ($cases as [$model, $attributes, $extraAbilities]) {
        $record = (new $model)->forceFill(['company_id' => $this->company->id] + $attributes);

        expect($this->owner->can('update', $record))->toBeTrue()
            ->and($accountant->can('update', $record))->toBeTrue()
            ->and($legacyMember->can('update', $record))->toBeFalse()
            ->and($legacyMember->can('view', $record))->toBeTrue();

        foreach ($extraAbilities as $ability) {
            expect($accountant->can($ability, $record))->toBeTrue()
                ->and($legacyMember->can($ability, $record))->toBeFalse();
        }
    }
});

// ---------- Configuration: admins only ----------

it('restricts accounting configuration to company admins', function () {
    $accountant = attachMember($this->company, 'accountant');

    $account = new Account(['company_id' => $this->company->id]);
    $account->setRelation('fiscalYear', $this->fiscalYear);

    $journal = new Journal(['company_id' => $this->company->id]);
    $journal->setRelation('fiscalYear', $this->fiscalYear);

    $taxRate = new TaxRate(['company_id' => $this->company->id]);
    $paymentMethod = new PaymentMethod(['company_id' => $this->company->id]);
    $settings = new CompanyAccountingSetting(['company_id' => $this->company->id]);

    foreach ([
        [$account, ['update', 'delete']],
        [$journal, ['update', 'activate']],
        [$taxRate, ['update', 'setDefault']],
        [$paymentMethod, ['update', 'setDefault']],
        [$settings, ['update']],
    ] as [$record, $abilities]) {
        expect($accountant->can('view', $record))->toBeTrue();

        foreach ($abilities as $ability) {
            expect($accountant->can($ability, $record))->toBeFalse()
                ->and($this->owner->can($ability, $record))->toBeTrue();
        }
    }
});

it('allows only admins to manage fiscal years and periods', function () {
    $accountant = attachMember($this->company, 'accountant');

    $period = new AccountingPeriod([
        'fiscal_year_id' => $this->fiscalYear->id,
        'is_open' => true,
        'is_closed' => false,
    ]);
    $period->setRelation('fiscalYear', $this->fiscalYear);

    expect($accountant->can('view', $period))->toBeTrue()
        ->and($accountant->can('close', $period))->toBeFalse()
        ->and($this->owner->can('close', $period))->toBeTrue();

    $closedFiscalYear = new FiscalYear([
        'company_id' => $this->company->id,
        'is_closed' => true,
    ]);

    expect($this->owner->can('close', $closedFiscalYear))->toBeFalse()
        ->and($this->owner->can('update', $closedFiscalYear))->toBeFalse()
        ->and($accountant->can('update', $this->fiscalYear))->toBeFalse()
        ->and($accountant->can('view', $this->fiscalYear))->toBeTrue();
});

it('restricts company settings and membership management to admins', function () {
    $accountant = attachMember($this->company, 'accountant');

    expect($accountant->can('update', $this->company))->toBeFalse()
        ->and($accountant->can('manageMembers', $this->company))->toBeFalse()
        ->and($this->owner->can('manageMembers', $this->company))->toBeTrue();
});

// ---------- Membership service ----------

it('protects the last active admin from demotion, deactivation and removal', function () {
    $soleAdmin = attachMember($this->company, 'admin');

    // Owner demotes the OTHER admin while another admin remains -> fine.
    $this->service->changeRole($this->company, $soleAdmin, CompanyRole::Accountant);
    expect($soleAdmin->hasCompanyRole($this->company, CompanyRole::Accountant))->toBeTrue();

    // Now the owner is the last active admin.
    try {
        $this->service->changeRole($this->company, $this->owner, CompanyRole::Viewer);
        $this->fail('Expected demotion of last admin to fail.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('administrateur');
    }

    try {
        $this->service->deactivate($this->company, $this->owner);
        $this->fail('Expected deactivation of last admin to fail.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('administrateur');
    }

    try {
        $this->service->remove($this->company, $this->owner);
        $this->fail('Expected removal of last admin to fail.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('administrateur');
    }

    $pivot = DB::table('company_user')
        ->where('company_id', $this->company->id)
        ->where('user_id', $this->owner->id)
        ->first();

    expect($pivot->role)->toBe('admin')
        ->and((bool) $pivot->is_active)->toBeTrue();
});

it('changes roles, revokes, restores and removes memberships', function () {
    $accountant = attachMember($this->company, 'accountant');

    $this->service->changeRole($this->company, $accountant, CompanyRole::Viewer);
    expect($accountant->hasCompanyRole($this->company, CompanyRole::Viewer))->toBeTrue();

    $this->service->deactivate($this->company, $accountant);
    expect($accountant->isActiveCompanyMember($this->company))->toBeFalse();

    $this->service->activate($this->company, $accountant);
    expect($accountant->isActiveCompanyMember($this->company))->toBeTrue();

    $this->service->remove($this->company, $accountant);
    expect(DB::table('company_user')
        ->where('company_id', $this->company->id)
        ->where('user_id', $accountant->id)
        ->exists())->toBeFalse();
});

it('refuses to manage users who are not members', function () {
    $outsider = User::factory()->create();

    expect(fn () => $this->service->changeRole($this->company, $outsider, CompanyRole::Viewer))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $this->service->deactivate($this->company, $outsider))
        ->toThrow(RuntimeException::class);
});

// ---------- Cross-company isolation ----------

it('denies access to resources owned by another company', function () {
    $otherCompany = Company::create([
        'name' => 'Autre Societe',
        'currency' => 'TND',
        'is_active' => true,
    ]);
    $otherCompany->users()->attach(User::factory()->create(), ['role' => 'admin', 'is_active' => true]);

    $foreignCustomer = new Customer(['company_id' => $otherCompany->id]);

    expect($this->owner->can('view', $foreignCustomer))->toBeFalse()
        ->and($this->owner->can('update', $foreignCustomer))->toBeFalse()
        ->and($this->owner->isCompanyAdmin($otherCompany))->toBeFalse();
});

// ---------- Pages & routes ----------

it('shows the members page only to admins', function () {
    $accountant = attachMember($this->company, 'accountant');

    $this->actingAs($this->owner);

    Livewire::test(Members::class)
        ->assertOk()
        ->assertSee('Membres de la société')
        ->assertSee($this->owner->email);

    $this->actingAs($accountant);

    Livewire::test(Members::class)
        ->assertForbidden();

    $this->actingAs($accountant);
    $this->get(route('companies.members'))
        ->assertForbidden();
});

it('blocks non-admin members from mutating members through the page', function () {
    $accountant = attachMember($this->company, 'accountant');
    $viewer = attachMember($this->company, 'viewer');

    $this->actingAs($accountant);

    Livewire::withQueryParams([])
        ->test(Members::class)
        ->assertForbidden();

    expect(DB::table('company_user')
        ->where('user_id', $viewer->id)
        ->value('role'))->toBe('viewer');
});

it('keeps deactivated members out of the current company context', function () {
    $accountant = attachMember($this->company, 'accountant');
    $this->service->deactivate($this->company, $accountant);

    $resolved = app(CurrentCompany::class)->get($accountant);

    expect($resolved)->toBeNull();
});
