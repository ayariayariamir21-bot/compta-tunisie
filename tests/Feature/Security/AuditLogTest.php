<?php

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Livewire\AccountingPeriods\Index as PeriodsIndex;
use App\Livewire\AuditLogs\Index as AuditLogsIndex;
use App\Livewire\Companies\Edit as CompaniesEdit;
use App\Livewire\Companies\Index as CompaniesIndex;
use App\Livewire\FiscalYears\Create as FiscalYearsCreate;
use App\Livewire\FiscalYears\Edit as FiscalYearsEdit;
use App\Livewire\FiscalYears\Index as FiscalYearsIndex;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\AccountingSettingsService;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PaymentMethodService;
use App\Services\Accounting\TaxRateService;
use App\Services\CompanyMembershipService;
use App\Services\CreditNoteService;
use App\Services\CustomerPaymentService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoicePostingService;
use App\Services\PurchaseInvoiceService;
use App\Services\SalesInvoicePostingService;
use App\Services\Security\AuditLogService;
use App\Services\SupplierPaymentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->email_verified_at = now();
    $this->owner->save();

    $this->company = Company::create([
        'name' => 'Societe Audit',
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
        'code' => 'VTE',
        'name' => 'Ventes',
        'type' => 'ventes',
        'is_active' => true,
    ]);

    $this->journalAchats = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'ACH',
        'name' => 'Achats',
        'type' => 'achats',
        'is_active' => true,
    ]);

    $this->journalOD = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'OD',
        'name' => 'Operations diverses',
        'type' => 'operations_diverses',
        'is_active' => true,
    ]);

    $this->account411 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '411000',
        'name' => 'Clients',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account401 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '401000',
        'name' => 'Fournisseurs',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->account512 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '512000',
        'name' => 'Banque',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account607 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '607000',
        'name' => 'Achats de marchandises',
        'account_type' => 'expense',
        'is_active' => true,
    ]);

    $this->account707 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '707000',
        'name' => 'Ventes de marchandises',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $this->account4457 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445700',
        'name' => 'TVA collectee',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'company_id' => $this->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $this->account411->id,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'FO001',
        'name' => 'Fournisseur Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $this->account401->id,
        'is_active' => true,
    ]);

    $this->taxRate = TaxRate::create([
        'company_id' => $this->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'sales_tax_account_id' => $this->account4457->id,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'sale_price' => '25.000',
        'purchase_price' => '10.000',
        'sales_account_id' => $this->account707->id,
        'purchase_account_id' => $this->account607->id,
        'tax_rate_id' => $this->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);
});

function auditAttachMember(Company $company, string $role, bool $isActive = true): User
{
    $user = User::factory()->create();
    $user->email_verified_at = now();
    $user->save();

    $company->users()->attach($user, ['role' => $role, 'is_active' => $isActive]);

    return $user;
}

function auditRows(): Collection
{
    return AuditLog::query()->orderBy('id')->get();
}

function auditLastRow(string $action): ?AuditLog
{
    return AuditLog::query()->where('action', $action)->orderByDesc('id')->first();
}

function auditMakeInvoiceData($test): array
{
    return [
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'notes' => 'Notes test',
        'terms' => 'Termes test',
        'created_by' => $test->owner->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => '10.000',
                'unit' => 'unit',
                'unit_price' => '25.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => $test->taxRate->id,
            ],
        ],
    ];
}

function auditDraftInvoice($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    return $service->createDraft(auditMakeInvoiceData($test));
}

function auditPostedInvoice($test): Invoice
{
    $service = new InvoiceService(new SalesInvoicePostingService(
        new JournalEntryService
    ));

    $invoice = $service->createDraft(auditMakeInvoiceData($test));

    return $service->post($invoice, $test->owner->id);
}

// ---------- Append-only model ----------

it('forbids updating an audit log entry', function () {
    $log = AuditLog::create(['action' => 'login', 'description' => 'Connexion']);

    expect(fn () => $log->update(['description' => 'Tampered']))
        ->toThrow(RuntimeException::class);
});

it('forbids deleting an audit log entry', function () {
    $log = AuditLog::create(['action' => 'login', 'description' => 'Connexion']);

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});

// ---------- Enum helpers ----------

it('exposes French labels for every action', function () {
    expect(AuditAction::InvoicePosted->label())->toBe('Facture comptabilisée')
        ->and(AuditAction::Login->label())->toBeString()
        ->and(AuditAction::RoleChanged->label())->toBeString();
});

it('flags security sensitive actions correctly', function () {
    expect(AuditAction::Login->isSecuritySensitive())->toBeTrue()
        ->and(AuditAction::RoleChanged->isSecuritySensitive())->toBeTrue()
        ->and(AuditAction::MemberRemoved->isSecuritySensitive())->toBeTrue()
        ->and(AuditAction::InvoicePosted->isSecuritySensitive())->toBeFalse()
        ->and(AuditAction::SettingsUpdated->isSecuritySensitive())->toBeFalse();
});

it('builds filter options as value and label pairs', function () {
    $options = AuditAction::optionsForFilter();

    expect($options)->toHaveCount(count(AuditAction::cases()))
        ->and($options[0])->toHaveKeys(['value', 'label']);
});

// ---------- Sanitization ----------

it('strips sensitive keys from payloads', function () {
    $service = new AuditLogService;

    $clean = $service->sanitizeData([
        'name' => 'Client',
        'password' => 'supersecret',
        'two_factor_secret' => 'AAAA',
        'api_token' => 'BBBB',
        'recovery_code_key' => 'CCCC',
        'X-Debug-Token' => 'DDDD',
    ]);

    expect($clean)->toBe(['name' => 'Client']);
});

it('keeps scalars and nulls but drops arrays and objects', function () {
    $service = new AuditLogService;

    $clean = $service->sanitizeData([
        'total' => '250.000',
        'quantity' => 3,
        'active' => true,
        'note' => null,
        'lines' => ['nested'],
        'object' => new stdClass,
    ]);

    expect($clean)->toBe([
        'total' => '250.000',
        'quantity' => 3,
        'active' => true,
        'note' => null,
    ]);
});

it('truncates overly long strings', function () {
    $service = new AuditLogService;

    $clean = $service->sanitizeData(['blob' => str_repeat('a', 3000)]);

    expect(strlen((string) $clean['blob']))->toBe(2003)
        ->and($clean['blob'])->toEndWith('...');
});

it('caps the number of stored keys', function () {
    $service = new AuditLogService;

    $payload = [];

    foreach (range(1, 61) as $i) {
        $payload['field_'.$i] = $i;
    }

    expect(count((array) $service->sanitizeData($payload)))->toBe(60);
});

it('returns null when nothing survives sanitization', function () {
    $service = new AuditLogService;

    expect($service->sanitizeData(null))->toBeNull()
        ->and($service->sanitizeData(['password' => 'x']))->toBeNull()
        ->and($service->sanitizeMetadata([]))->toBeNull();
});

it('snapshots models into plain scalar columns', function () {
    $service = new AuditLogService;

    $snapshot = $service->snapshot($this->account411, ['code', 'name', 'account_type', 'created_at']);

    expect($snapshot['code'])->toBe('411000')
        ->and($snapshot['account_type'])->toBe('asset')
        ->and($snapshot['created_at'])->toBeString();
});

// ---------- Authentication events ----------

it('records successful logins', function () {
    $this->post(route('login.store'), [
        'email' => $this->owner->email,
        'password' => 'password',
    ]);

    $row = auditLastRow('login');

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->owner->id);
});

it('records failed login attempts with the attempted email', function () {
    $this->post(route('login.store'), [
        'email' => $this->owner->email,
        'password' => 'definitely-wrong-password',
    ]);

    $row = auditLastRow('login_failed');

    expect($row)->not->toBeNull()
        ->and($row->metadata['attempted_email'] ?? null)->toBe($this->owner->email)
        ->and($row->user_id)->toBeNull();
});

it('records logouts', function () {
    $this->actingAs($this->owner)->post(route('logout'));

    $row = auditLastRow('logout');

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->owner->id);
});

it('records two factor enable and disable events', function () {
    TwoFactorAuthenticationEnabled::dispatch($this->owner);
    TwoFactorAuthenticationDisabled::dispatch($this->owner);

    expect(auditLastRow('two_factor_enabled')->user_id)->toBe($this->owner->id)
        ->and(auditLastRow('two_factor_disabled')->user_id)->toBe($this->owner->id);
});

// ---------- Membership events ----------

it('records role changes with before and after metadata', function () {
    $this->actingAs($this->owner);

    $accountant = auditAttachMember($this->company, 'accountant');

    app(CompanyMembershipService::class)->changeRole($this->company, $accountant, CompanyRole::Viewer);

    $row = auditLastRow('role_changed');

    expect($row->entity_type)->toBe('User')
        ->and((int) $row->entity_id)->toBe($accountant->id)
        ->and($row->before_data['role'] ?? null)->toBeString()
        ->and($row->after_data['role'] ?? null)->toBeString();
});

it('records member deactivation and reactivation', function () {
    $this->actingAs($this->owner);

    $member = auditAttachMember($this->company, 'accountant');
    $service = app(CompanyMembershipService::class);

    $service->deactivate($this->company, $member);
    $service->activate($this->company, $member);

    expect(auditLastRow('member_deactivated')->entity_id)->toBe($member->id)
        ->and(auditLastRow('member_activated')->entity_id)->toBe($member->id);
});

it('records member removal', function () {
    $this->actingAs($this->owner);

    $member = auditAttachMember($this->company, 'viewer');

    app(CompanyMembershipService::class)->remove($this->company, $member);

    $row = auditLastRow('member_removed');

    expect($row->entity_id)->toBe($member->id)
        ->and(AuditAction::tryFrom($row->action)?->isSecuritySensitive())->toBeTrue();
});

// ---------- Accounting structure events ----------

it('records account creation, update and deactivation', function () {
    $service = app(AccountService::class);

    $account = $service->createAccount($this->company, $this->fiscalYear, [
        'code' => '530000',
        'name' => 'Caisse',
        'account_type' => 'asset',
    ]);

    $service->updateAccount($account, [
        'code' => '530000',
        'name' => 'Caisse principale',
        'account_type' => 'asset',
    ]);

    $service->toggleActive($account);
    $service->toggleActive($account);

    expect(auditLastRow('account_created')->after_data['code'])->toBe('530000')
        ->and(auditLastRow('account_updated')->before_data['name'])->toBe('Caisse')
        ->and(auditLastRow('account_updated')->after_data['name'])->toBe('Caisse principale')
        ->and(AuditLog::where('action', 'account_deactivated')->count())->toBe(1);
});

it('records journal creation and update', function () {
    $service = app(JournalService::class);

    $journal = $service->createJournal($this->company, $this->fiscalYear, [
        'code' => 'BQE',
        'name' => 'Journal de banque',
        'type' => 'banque',
    ]);

    $service->updateJournal($journal, [
        'code' => 'BQE',
        'name' => 'Banque principale',
        'type' => 'banque',
    ]);

    expect(auditLastRow('journal_created')->after_data['code'])->toBe('BQE')
        ->and(auditLastRow('journal_updated')->before_data['name'])->toBe('Journal de banque');
});

it('records tax rate creation and update', function () {
    $service = app(TaxRateService::class);

    $taxRate = $service->create($this->company, [
        'code' => 'tva13',
        'name' => 'TVA 13%',
        'rate' => 13.0,
        'type' => 'vat',
    ]);

    $service->update($taxRate, [
        'code' => 'TVA13',
        'name' => 'TVA 13%',
        'rate' => 13.5,
        'type' => 'vat',
    ]);

    expect(auditLastRow('tax_rate_created'))->not->toBeNull()
        ->and((float) auditLastRow('tax_rate_updated')->before_data['rate'])->toBe(13.0)
        ->and((float) auditLastRow('tax_rate_updated')->after_data['rate'])->toBe(13.5);
});

it('records payment method creation and update', function () {
    $service = app(PaymentMethodService::class);

    $method = $service->createPaymentMethod($this->company, [
        'code' => 'chk',
        'name' => 'Cheque',
        'type' => 'cheque',
    ]);

    $service->updatePaymentMethod($method, [
        'code' => 'CHK',
        'name' => 'Cheque bancaire',
        'type' => 'cheque',
    ]);

    expect(auditLastRow('payment_method_created')->after_data['code'])->toBe('CHK')
        ->and(auditLastRow('payment_method_updated')->before_data['name'])->toBe('Cheque');
});

it('records accounting settings updates with before snapshot', function () {
    $service = app(AccountingSettingsService::class);

    $settings = $service->getForCompany($this->company);

    $service->updateSettings($settings, ['invoice_prefix' => 'FACZ'], $this->company->id, $this->fiscalYear->id);

    $row = auditLastRow('settings_updated');

    expect($row)->not->toBeNull()
        ->and($row->before_data['invoice_prefix'] ?? null)->toBe('FAC')
        ->and($row->after_data['invoice_prefix'] ?? null)->toBe('FACZ');
});

// ---------- Fiscal years, periods and companies (Livewire) ----------

it('records fiscal year creation through the Livewire form', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    Livewire::test(FiscalYearsCreate::class)
        ->set('name', 'Exercice 2027')
        ->set('code', '2027')
        ->set('start_date', '2027-01-01')
        ->set('end_date', '2027-12-31')
        ->call('store');

    $row = auditLastRow('fiscal_year_created');

    expect($row)->not->toBeNull()
        ->and($row->company_id)->toBe($this->company->id)
        ->and(FiscalYear::where('code', '2027')->where('company_id', $this->company->id)->exists())->toBeTrue();
});

it('records fiscal year updates with before and after snapshots', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    Livewire::test(FiscalYearsEdit::class, ['fiscalYearId' => $this->fiscalYear->id])
        ->set('name', 'Exercice 2026 revise')
        ->call('update');

    $row = auditLastRow('fiscal_year_updated');

    expect($row->before_data['name'])->toBe('Exercice 2026')
        ->and($row->after_data['name'])->toBe('Exercice 2026 revise');
});

it('records fiscal year activation', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    $newYear = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2027',
        'code' => '2027',
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        'is_active' => false,
        'is_closed' => false,
    ]);

    Livewire::test(FiscalYearsIndex::class)->call('activate', $newYear);

    $row = auditLastRow('fiscal_year_activated');

    expect($row)->not->toBeNull()
        ->and((int) $row->entity_id)->toBe($newYear->id);
});

it('records accounting period closure', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    Livewire::test(PeriodsIndex::class)->call('close', $this->period);

    $row = auditLastRow('accounting_period_closed');

    expect($row)->not->toBeNull()
        ->and((int) $row->entity_id)->toBe($this->period->id)
        ->and($this->period->fresh()->is_closed)->toBeTrue();
});

it('records company updates with before and after snapshots', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    Livewire::test(CompaniesEdit::class, ['companyId' => $this->company->id])
        ->set('name', 'Societe Audit Renommee')
        ->call('update');

    $row = auditLastRow('company_updated');

    expect($row->before_data['name'])->toBe('Societe Audit')
        ->and($row->after_data['name'])->toBe('Societe Audit Renommee');
});

it('records company deactivation and activation', function () {
    config(['app.debug' => false]);

    $this->actingAs($this->owner);

    $toDeactivate = Company::create(['name' => 'Societe A', 'currency' => 'TND', 'is_active' => true]);
    $toDeactivate->users()->attach($this->owner, ['role' => 'admin', 'is_active' => true]);

    $toActivate = Company::create(['name' => 'Societe B', 'currency' => 'TND', 'is_active' => false]);
    $toActivate->users()->attach($this->owner, ['role' => 'admin', 'is_active' => true]);

    Livewire::test(CompaniesIndex::class)->call('deactivate', $toDeactivate);
    Livewire::test(CompaniesIndex::class)->call('activate', $toActivate);

    expect(auditLastRow('company_deactivated')->entity_id)->toBe($toDeactivate->id)
        ->and(auditLastRow('company_activated')->entity_id)->toBe($toActivate->id);
});

// ---------- Document lifecycle events ----------

it('records invoice creation with a sanitized after snapshot', function () {
    $invoice = auditDraftInvoice($this);

    $row = auditLastRow('invoice_created');

    expect($row->entity_type)->toBe('Invoice')
        ->and((int) $row->entity_id)->toBe($invoice->id)
        ->and($row->company_id)->toBe($this->company->id)
        ->and($row->after_data['invoice_number'] ?? null)->toBe($invoice->invoice_number)
        ->and($row->after_data)->not->toHaveKey('password');
});

it('records draft invoice updates with before and after', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(new JournalEntryService));
    $invoice = $service->createDraft(auditMakeInvoiceData($this));

    $data = auditMakeInvoiceData($this);
    $data['due_date'] = '2026-03-01';

    $service->updateDraft($invoice, collect($data)->except(['company_id', 'created_by'])->all());

    $row = auditLastRow('invoice_updated');

    expect((string) $row->before_data['due_date'])->toStartWith('2026-02-14')
        ->and((string) $row->after_data['due_date'])->toStartWith('2026-03-01');
});

it('records invoice posting with journal entry metadata', function () {
    $invoice = auditPostedInvoice($this);

    $row = auditLastRow('invoice_posted');

    expect($invoice->status)->toBe(InvoiceStatus::POSTED)
        ->and($row->entity_type)->toBe('Invoice')
        ->and($row->metadata['invoice_number'] ?? null)->toBe($invoice->invoice_number)
        ->and($row->metadata['total'] ?? null)->toBe((string) $invoice->total)
        ->and($row->metadata['journal_entry_number'] ?? null)->toBe($invoice->journalEntry?->entry_number);
});

it('records invoice cancellation', function () {
    $service = new InvoiceService(new SalesInvoicePostingService(new JournalEntryService));
    $invoice = auditDraftInvoice($this);

    $service->cancel($invoice);

    $row = auditLastRow('invoice_cancelled');

    expect($row)->not->toBeNull()
        ->and($row->entity_id)->toBe($invoice->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::CANCELLED);
});

it('records credit note creation from a posted invoice', function () {
    $invoice = auditPostedInvoice($this);

    app(CreditNoteService::class)->createFromInvoice(
        $invoice,
        $this->company->id,
        $this->fiscalYear->id,
        $this->period->id,
        $this->journal->id,
        $this->owner->id,
        '2026-01-20',
    );

    $row = auditLastRow('credit_note_created');

    expect($row)->not->toBeNull()
        ->and($row->company_id)->toBe($this->company->id);
});

it('records purchase invoice creation', function () {
    $service = new PurchaseInvoiceService(new PurchaseInvoicePostingService(new JournalEntryService));

    $service->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journalAchats->id,
        'invoice_date' => '2026-01-15',
        'supplier_invoice_number' => 'SI-001',
        'created_by' => $this->owner->id,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Produit Test',
                'quantity' => '5.000',
                'unit' => 'unit',
                'unit_price' => '10.000',
                'discount_percent' => '0.000',
            ],
        ],
    ]);

    expect(auditLastRow('purchase_invoice_created'))->not->toBeNull();
});

it('records customer payment creation', function () {
    $method = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'VIR',
        'name' => 'Virement',
        'type' => 'bank_transfer',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 1,
    ]);

    app(CustomerPaymentService::class)->createDraft([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'payment_method_id' => $method->id,
        'journal_id' => $this->journal->id,
        'destination_account_id' => $this->account512->id,
        'payment_date' => '2026-01-15',
        'amount' => '150.000',
        'created_by' => $this->owner->id,
    ]);

    $row = auditLastRow('customer_payment_created');

    expect($row)->not->toBeNull()
        ->and($row->after_data['amount'] ?? null)->toBe('150.000');
});

it('records supplier payment creation', function () {
    $method = PaymentMethod::create([
        'company_id' => $this->company->id,
        'code' => 'ESP',
        'name' => 'Especes',
        'type' => 'cash',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    app(SupplierPaymentService::class)->createDraft([
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'payment_method_id' => $method->id,
        'journal_id' => $this->journalOD->id,
        'destination_account_id' => $this->account512->id,
        'payment_date' => '2026-01-15',
        'amount' => '80.000',
        'created_by' => $this->owner->id,
    ]);

    expect(auditLastRow('supplier_payment_created'))->not->toBeNull();
});

it('records expense creation', function () {
    app(ExpenseService::class)->createDraft([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journalOD->id,
        'expense_date' => '2026-01-15',
        'created_by' => $this->owner->id,
        'lines' => [
            [
                'expense_account_id' => $this->account607->id,
                'label' => 'Achat fournitures',
                'quantity' => '1.000',
                'unit_price' => '50.000',
                'discount_percent' => '0.000',
            ],
        ],
    ]);

    $row = auditLastRow('expense_created');

    expect($row)->not->toBeNull()
        ->and($row->entity_type)->toBe('Expense');
});

it('records journal entry creation, posting and cancellation', function () {
    $service = new JournalEntryService;

    $lines = [
        ['account_id' => $this->account512->id, 'description' => null, 'debit' => '100.000', 'credit' => '0.000'],
        ['account_id' => $this->account707->id, 'description' => null, 'debit' => '0.000', 'credit' => '100.000'],
    ];

    $posted = $service->createDraft(
        $this->company,
        $this->fiscalYear,
        $this->period,
        ['journal_id' => $this->journalOD->id, 'entry_date' => '2026-01-15', 'description' => 'OD test'],
        $lines,
        $this->owner->id,
    );

    $service->post($posted);

    $draft = $service->createDraft(
        $this->company,
        $this->fiscalYear,
        $this->period,
        ['journal_id' => $this->journalOD->id, 'entry_date' => '2026-01-16', 'description' => 'OD brouillon'],
        $lines,
        $this->owner->id,
    );

    $service->cancel($draft);

    expect(AuditLog::query()->where('action', 'journal_entry_created')->orderBy('id')->first()->entity_id)->toBe($posted->id)
        ->and(auditLastRow('journal_entry_posted'))->not->toBeNull()
        ->and(auditLastRow('journal_entry_cancelled')->entity_id)->toBe($draft->id);
});

// ---------- Rollback atomicity ----------

it('rolls back both the posting and its audit row when a failure occurs mid-transaction', function () {
    $invoice = auditDraftInvoice($this);

    Invoice::updated(function (Invoice $updating): void {
        if ($updating->status === InvoiceStatus::POSTED) {
            throw new RuntimeException('Simulated mid-transaction failure.');
        }
    });

    $service = new InvoiceService(new SalesInvoicePostingService(new JournalEntryService));

    expect(fn () => $service->post($invoice, $this->owner->id))
        ->toThrow(RuntimeException::class, 'Simulated mid-transaction failure.');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::DRAFT)
        ->and(JournalEntry::count())->toBe(0)
        ->and(AuditLog::where('action', 'invoice_posted')->count())->toBe(0);
});

it('writes no audit row when the surrounding transaction is rolled back', function () {
    DB::beginTransaction();

    try {
        auditDraftInvoice($this);
    } finally {
        DB::rollBack();
    }

    expect(Invoice::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

// ---------- Policy ----------

it('restricts audit journal access to admins and accountants', function () {
    $admin = $this->owner;
    $accountant = auditAttachMember($this->company, 'accountant');
    $viewer = auditAttachMember($this->company, 'viewer');
    $outsider = auditAttachMember(Company::create(['name' => 'Externe', 'currency' => 'TND', 'is_active' => true]), 'admin');

    expect($admin->can('viewAny', [AuditLog::class, $this->company]))->toBeTrue()
        ->and($admin->can('viewAny', [AuditLog::class, $this->company->id]))->toBeTrue()
        ->and($accountant->can('viewAny', [AuditLog::class, $this->company]))->toBeTrue()
        ->and($viewer->can('viewAny', [AuditLog::class, $this->company]))->toBeFalse()
        ->and($outsider->can('viewAny', [AuditLog::class, $this->company]))->toBeFalse();
});

it('hides security sensitive entries from accountants but shows them to admins', function () {
    $admin = $this->owner;
    $accountant = auditAttachMember($this->company, 'accountant');
    $viewer = auditAttachMember($this->company, 'viewer');

    $sensitive = AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $admin->id,
        'action' => 'role_changed',
        'description' => 'Changement de role',
    ]);

    $business = AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $admin->id,
        'action' => 'invoice_created',
        'entity_type' => 'Invoice',
        'entity_id' => 1,
        'description' => 'Facture creee',
    ]);

    expect($admin->can('view', $sensitive))->toBeTrue()
        ->and($admin->can('view', $business))->toBeTrue()
        ->and($accountant->can('view', $sensitive))->toBeFalse()
        ->and($accountant->can('view', $business))->toBeTrue()
        ->and($viewer->can('view', $business))->toBeFalse();
});

// ---------- UI ----------

it('serves the audit journal page to admins with every row', function () {
    AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'role_changed',
        'description' => 'Changement de role membre',
    ]);

    AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'invoice_created',
        'description' => 'Creation facture brouillon',
    ]);

    $this->actingAs($this->owner)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee('Changement de role membre')
        ->assertSee('Creation facture brouillon');
});

it('filters security sensitive rows out of the accountant view', function () {
    $accountant = auditAttachMember($this->company, 'accountant');

    AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'role_changed',
        'description' => 'Changement de role membre',
    ]);

    AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'invoice_created',
        'description' => 'Creation facture brouillon',
    ]);

    $this->actingAs($accountant);

    Livewire::test(AuditLogsIndex::class)
        ->assertSee('Creation facture brouillon')
        ->assertDontSee('Changement de role membre');
});

it('denies viewers and guests access to the audit journal page', function () {
    $viewer = auditAttachMember($this->company, 'viewer');

    $this->get(route('audit-logs.index'))->assertRedirect(route('login'));

    $this->actingAs($viewer);

    Livewire::test(AuditLogsIndex::class)->assertStatus(403);
});

it('supports action filtering and detail selection while blocking cross company reads', function () {
    $this->actingAs($this->owner);

    $mine = AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'invoice_created',
        'description' => 'Facture locale',
    ]);

    AuditLog::create([
        'company_id' => $this->company->id,
        'user_id' => $this->owner->id,
        'action' => 'expense_created',
        'description' => 'Depense locale',
    ]);

    $foreignCompany = Company::create(['name' => 'Autre', 'currency' => 'TND', 'is_active' => true]);
    $foreign = AuditLog::create([
        'company_id' => $foreignCompany->id,
        'action' => 'invoice_created',
        'description' => 'Facture etrangere',
    ]);

    Livewire::test(AuditLogsIndex::class)
        ->set('action', 'invoice_created')
        ->assertSee('Facture locale')
        ->assertDontSee('Depense locale')
        ->call('showDetail', $mine->id)
        ->assertSet('selectedLogId', $mine->id);

    $thrown = null;

    try {
        Livewire::test(AuditLogsIndex::class)->call('showDetail', $foreign->id);
    } catch (HttpException $e) {
        $thrown = $e;
    }

    if ($thrown !== null) {
        expect($thrown->getStatusCode())->toBe(403);
    } else {
        Livewire::test(AuditLogsIndex::class)
            ->call('showDetail', $foreign->id)
            ->assertStatus(403);
    }
});
