<?php

use App\Enums\CompanyRole;
use App\Enums\NotificationSeverity;
use App\Livewire\FiscalYears\Index;
use App\Livewire\Notifications\Dropdown;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\SecurityNotification;
use App\Services\Accounting\CustomerPaymentPostingService;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\SupplierPaymentPostingService;
use App\Services\CompanyMembershipService;
use App\Services\CustomerPaymentService;
use App\Services\InvoiceService;
use App\Services\PurchaseInvoicePostingService;
use App\Services\PurchaseInvoiceService;
use App\Services\SalesInvoicePostingService;
use App\Services\Security\NotificationService;
use App\Services\SupplierPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->notificationService = new NotificationService;

    $this->admin = notifVerifiedUser();
    $this->accountant = notifVerifiedUser();
    $this->viewer = notifVerifiedUser();
    $this->outsider = notifVerifiedUser();

    $this->company = notifCreateCompany('Societe Notifications');
    $this->otherCompany = notifCreateCompany('Societe Autre');

    $this->company->users()->attach($this->admin, ['role' => 'admin', 'is_active' => true]);
    $this->company->users()->attach($this->accountant, ['role' => 'accountant', 'is_active' => true]);
    $this->company->users()->attach($this->viewer, ['role' => 'viewer', 'is_active' => true]);
    $this->company->users()->attach($this->outsider, ['role' => 'viewer', 'is_active' => false]);

    $this->otherAdmin = notifVerifiedUser();
    $this->otherCompany->users()->attach($this->otherAdmin, ['role' => 'admin', 'is_active' => true]);

    notifBuildAccountingFixture($this);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function notifVerifiedUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function notifCreateCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'currency' => 'TND',
        'is_active' => true,
    ]);
}

function notifPersonalSecurityNotification(User $recipient, string $title = 'Test personnel'): SecurityNotification
{
    return new SecurityNotification(
        title: $title,
        message: "Message pour {$recipient->email}.",
        severity: NotificationSeverity::Info,
        dedupKey: 'personal.test.'.$recipient->id.'.'.$title,
    );
}

function notifSecondSecurityNotification(): SecurityNotification
{
    return new SecurityNotification(
        title: 'Test secondaire',
        message: 'Seconde notification de contrôle.',
        severity: NotificationSeverity::Info,
        dedupKey: 'personal.test.secondary',
    );
}

/**
 * Complete accounting scaffolding so real posting services can be exercised.
 */
function notifBuildAccountingFixture($test): void
{
    $test->fiscalYear = FiscalYear::create([
        'company_id' => $test->company->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
        'is_closed' => false,
    ]);

    $test->period = AccountingPeriod::create([
        'fiscal_year_id' => $test->fiscalYear->id,
        'name' => 'Janvier 2026',
        'code' => '2026-01',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_open' => true,
        'is_closed' => false,
    ]);

    $makeJournal = fn (string $code, string $name, string $type) => Journal::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'is_active' => true,
    ]);

    $test->journalVentes = $makeJournal('VTE', 'Ventes', 'ventes');
    $test->journalAchats = $makeJournal('ACH', 'Achats', 'achats');
    $test->journalBanque = $makeJournal('BQ', 'Banque', 'banque');

    $makeAccount = fn (string $code, string $name, string $type) => Account::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'code' => $code,
        'name' => $name,
        'account_type' => $type,
        'is_active' => true,
    ]);

    $test->account411 = $makeAccount('411000', 'Clients', 'asset');
    $test->account707 = $makeAccount('707000', 'Ventes de marchandises', 'revenue');
    $test->account4457 = $makeAccount('445700', 'TVA collectée', 'liability');
    $test->account401 = $makeAccount('401000', 'Fournisseurs', 'liability');
    $test->account607 = $makeAccount('607000', 'Achats de marchandises', 'expense');
    $test->account532 = $makeAccount('532000', 'Banque', 'asset');

    $test->customer = Customer::create([
        'company_id' => $test->company->id,
        'code' => 'CL001',
        'name' => 'Client Test',
        'customer_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $test->account411->id,
        'is_active' => true,
    ]);

    $test->supplier = Supplier::create([
        'company_id' => $test->company->id,
        'code' => 'FRS001',
        'name' => 'Fournisseur Test',
        'supplier_type' => 'individual',
        'country' => 'TN',
        'payment_terms_days' => 30,
        'account_id' => $test->account401->id,
        'is_active' => true,
    ]);

    $test->taxRate = TaxRate::create([
        'company_id' => $test->company->id,
        'code' => 'TVA19',
        'name' => 'TVA 19%',
        'type' => 'vat',
        'rate' => 19.0,
        'sales_tax_account_id' => $test->account4457->id,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);

    $test->product = Product::create([
        'company_id' => $test->company->id,
        'code' => 'PRD001',
        'name' => 'Produit Test',
        'type' => 'product',
        'unit' => 'unit',
        'sale_price' => '25.000',
        'purchase_price' => '10.000',
        'sales_account_id' => $test->account707->id,
        'purchase_account_id' => $test->account607->id,
        'tax_rate_id' => $test->taxRate->id,
        'is_active' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
    ]);

    $test->paymentMethod = PaymentMethod::create([
        'company_id' => $test->company->id,
        'code' => 'VIR',
        'name' => 'Virement',
        'type' => 'bank_transfer',
        'is_active' => true,
    ]);
}

function notifPostSalesInvoice($test): Invoice
{
    $service = new InvoiceService(
        new SalesInvoicePostingService(new JournalEntryService),
    );

    $invoice = $service->createDraft([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journalVentes->id,
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-14',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $test->admin->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => '1000.000',
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);

    return $service->post($invoice->fresh(), $test->admin->id)->fresh();
}

function notifPostPurchaseInvoice($test): PurchaseInvoice
{
    $service = new PurchaseInvoiceService(
        new PurchaseInvoicePostingService(new JournalEntryService),
    );

    $invoice = $service->createDraft([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journalAchats->id,
        'invoice_date' => '2026-01-16',
        'due_date' => '2026-02-15',
        'currency' => 'TND',
        'payment_terms_days' => 30,
        'created_by' => $test->admin->id,
        'lines' => [
            [
                'product_id' => $test->product->id,
                'description' => 'Produit Test',
                'quantity' => '500.000',
                'unit' => 'unit',
                'unit_price' => '1.000',
                'discount_percent' => '0.000',
                'tax_rate_id' => null,
            ],
        ],
    ]);

    return $service->post($invoice->fresh(), $test->admin->id)->fresh();
}

function notifPostCustomerPayment($test): CustomerPayment
{
    $service = new CustomerPaymentService(
        new CustomerPaymentPostingService(new JournalEntryService),
    );

    $payment = $service->createDraft([
        'company_id' => $test->company->id,
        'customer_id' => $test->customer->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->paymentMethod->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => '2026-01-20',
        'amount' => '100.000',
        'currency' => 'TND',
        'created_by' => $test->admin->id,
    ]);

    return $service->post($payment->fresh(), $test->admin->id)->fresh();
}

function notifPostSupplierPayment($test): SupplierPayment
{
    $service = new SupplierPaymentService(
        new SupplierPaymentPostingService(new JournalEntryService),
    );

    $payment = $service->createDraft([
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'payment_method_id' => $test->paymentMethod->id,
        'journal_id' => $test->journalBanque->id,
        'destination_account_id' => $test->account532->id,
        'payment_date' => '2026-01-21',
        'amount' => '50.000',
        'currency' => 'TND',
        'created_by' => $test->admin->id,
    ]);

    return $service->post($payment->fresh(), $test->admin->id)->fresh();
}

// ---------------------------------------------------------------------------
// Delivery & isolation
// ---------------------------------------------------------------------------

it('delivers a personal notification to its recipient', function () {
    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));

    expect($this->admin->notifications()->count())->toBe(1)
        ->and($this->admin->unreadNotifications()->count())->toBe(1);
});

it('never exposes another user notification on the index page', function () {
    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));
    $this->notificationService->notifyUser(
        $this->accountant,
        notifPersonalSecurityNotification($this->accountant, 'Notification comptable privée'),
    );

    actingAs($this->admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Test personnel')
        ->assertDontSee('Notification comptable privée');

    actingAs($this->accountant)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Notification comptable privée')
        ->assertDontSee('Test personnel');
});

it('sends business notifications to operational roles only', function () {
    $invoice = notifPostSalesInvoice($this);

    // Admin posted â†’ excluded; accountant receives; viewer and inactive member do not.
    expect($this->accountant->notifications()->count())->toBe(1)
        ->and($this->viewer->notifications()->count())->toBe(0)
        ->and($this->outsider->notifications()->count())->toBe(0)
        ->and($invoice->status->value)->toBe('posted');
});

it('excludes inactive members from company notifications', function () {
    $inactive = notifVerifiedUser();
    $this->company->users()->attach($inactive, ['role' => 'accountant', 'is_active' => false]);

    $this->notificationService->notifyCompanyRoles(
        $this->company,
        [CompanyRole::Admin],
        notifPersonalSecurityNotification($this->admin),
    );

    expect($inactive->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(1);
});

it('isolates companies: another company admin receives nothing', function () {
    notifPostSalesInvoice($this);

    expect($this->otherAdmin->notifications()->count())->toBe(0)
        ->and($this->accountant->notifications()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Read state, counts, pagination, filters
// ---------------------------------------------------------------------------

it('reports unread counts correctly', function () {
    expect($this->notificationService->unreadCount($this->admin))->toBe(0);

    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));
    $this->notificationService->notifyUser($this->admin, notifSecondSecurityNotification());

    expect($this->notificationService->unreadCount($this->admin))->toBe(2);

    $first = $this->admin->unreadNotifications()->first();
    $this->notificationService->markAsRead($this->admin, (string) $first->id);

    expect($this->notificationService->unreadCount($this->admin))->toBe(1);
});

it('marks one notification as read and all as read', function () {
    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));
    $this->notificationService->notifyUser($this->admin, notifSecondSecurityNotification());

    $first = $this->admin->unreadNotifications()->first();

    $this->notificationService->markAsRead($this->admin, (string) $first->id);

    expect($this->admin->unreadNotifications()->count())->toBe(1);

    $this->notificationService->markAllAsRead($this->admin);

    expect($this->admin->unreadNotifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(2);
});

it('paginates the notifications page', function () {
    foreach (range(1, 20) as $index) {
        $this->notificationService->notifyUser($this->admin, new SecurityNotification(
            title: "Notif {$index}",
            message: 'Contenu '.$index,
            severity: NotificationSeverity::Info,
            dedupKey: "personal.test.{$index}",
        ));
    }

    actingAs($this->admin)
        ->get(route('notifications.index').'?page=2')
        ->assertOk()
        ->assertSee('Notif ');

    expect($this->admin->notifications()->count())->toBe(20);
});

it('filters by notification type on the index page', function () {
    notifPostSalesInvoice($this); // business notification for accountant

    $this->notificationService->notifyUser($this->accountant, notifPersonalSecurityNotification($this->accountant));

    actingAs($this->accountant)
        ->get(route('notifications.index', ['type' => 'security']))
        ->assertOk()
        ->assertSee('Test personnel')
        ->assertDontSee('Facture FAC');

    actingAs($this->accountant)
        ->get(route('notifications.index', ['state' => 'read']))
        ->assertOk()
        ->assertDontSee('Test personnel');
});

// ---------------------------------------------------------------------------
// Links and authorization of targets
// ---------------------------------------------------------------------------

it('resolves stored links to application routes', function () {
    $invoice = notifPostSalesInvoice($this);

    $notification = $this->accountant->notifications()->firstOrFail();

    $url = AppNotification::resolveLink($notification);

    expect($url)->not->toBeNull()
        ->and(str_contains((string) $url, '/invoices/'.$invoice->id))->toBeTrue();
});

it('keeps notification target routes policy-protected', function () {
    $invoice = notifPostSalesInvoice($this);

    $notification = $this->accountant->notifications()->firstOrFail();
    $url = (string) AppNotification::resolveLink($notification);
    $path = parse_url($url, PHP_URL_PATH);

    // A user with no membership cannot open the entity behind the link
    // (404 avoids revealing the entity's existence).
    actingAs($this->otherAdmin)
        ->get($path)
        ->assertNotFound();

    // The legitimate accountant can.
    actingAs($this->accountant)
        ->get($path)
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Event integrations
// ---------------------------------------------------------------------------

it('creates a security notification when a role changes', function () {
    app(CompanyMembershipService::class)->changeRole($this->company, $this->accountant, CompanyRole::Viewer);

    $notification = $this->accountant->notifications()->firstOrFail();
    $data = $notification->data;

    expect($data['title'])->toBe('Rôle modifié')
        ->and($data['message'])->toContain('Comptable')->toContain('Lecteur')
        ->and($data['company_id'])->toBe($this->company->id);
});

it('does not notify when the role did not change', function () {
    $membership = new CompanyMembershipService;
    $membership->changeRole($this->company, $this->accountant, CompanyRole::Viewer);
    $membership->changeRole($this->company, $this->accountant, CompanyRole::Viewer);

    expect($this->accountant->notifications()->count())->toBe(1);
});

it('creates a notification when two-factor authentication is enabled or disabled', function () {
    event(new TwoFactorAuthenticationEnabled($this->admin));
    event(new TwoFactorAuthenticationDisabled($this->admin));

    $titles = $this->admin->notifications()->latest()->pluck('data')->pluck('title')->all();

    expect(count($titles))->toBe(2)
        ->and($titles[1])->toBe('Authentification à deux facteurs');
});

it('creates accounting notifications when a fiscal year closes', function () {
    config(['app.debug' => false]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);

    actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('close', $this->fiscalYear);

    expect($this->accountant->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->count())->toBe(0) // actor excluded
        ->and($this->accountant->notifications()->first()->data['title'])->toBe('Exercice clôturé');
});

it('creates accounting notifications when an accounting period closes', function () {
    config(['app.debug' => false]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
        'current_accounting_period_id' => $this->period->id,
    ]);

    actingAs($this->admin);

    Livewire::test(App\Livewire\AccountingPeriods\Index::class)
        ->call('close', $this->period);

    expect($this->accountant->notifications()->count())->toBe(1)
        ->and($this->accountant->notifications()->first()->data['title'])->toBe('Période clôturée');
});

it('creates a business notification when an invoice is posted', function () {
    $invoice = notifPostSalesInvoice($this);

    $notification = $this->accountant->notifications()->firstOrFail();
    $data = $notification->data;

    expect($data['title'])->toBe('Facture comptabilisée')
        ->and($data['entity_type'])->toBe('invoice')
        ->and($data['entity_id'])->toBe($invoice->id)
        ->and($data['company_id'])->toBe($this->company->id);
});

it('creates a business notification when a customer payment is posted', function () {
    $payment = notifPostCustomerPayment($this);

    $data = $this->accountant->notifications()->firstOrFail()->data;

    expect($data['title'])->toBe('Règlement client comptabilisé')
        ->and($data['entity_type'])->toBe('customer_payment')
        ->and($data['entity_id'])->toBe($payment->id);
});

it('creates a business notification when a purchase invoice is posted', function () {
    $invoice = notifPostPurchaseInvoice($this);

    $data = $this->accountant->notifications()->firstOrFail()->data;

    expect($data['title'])->toContain("Facture d'achat")
        ->and($data['entity_type'])->toBe('purchase_invoice')
        ->and($data['entity_id'])->toBe($invoice->id);
});

it('creates a business notification when a supplier payment is posted', function () {
    $payment = notifPostSupplierPayment($this);

    $data = $this->accountant->notifications()->firstOrFail()->data;

    expect($data['title'])->toBe('Règlement fournisseur comptabilisé')
        ->and($data['entity_type'])->toBe('supplier_payment')
        ->and($data['entity_id'])->toBe($payment->id);
});

// ---------------------------------------------------------------------------
// Duplicate protection, rollback safety, payload hygiene
// ---------------------------------------------------------------------------

it('prevents duplicate unread notifications for the same event', function () {
    $notification = notifPersonalSecurityNotification($this->admin);

    $this->notificationService->notifyUser($this->admin, $notification);
    $this->notificationService->notifyUser($this->admin, $notification);

    expect($this->admin->notifications()->count())->toBe(1);
});

it('allows a new notification after the previous identical one was read', function () {
    $factory = fn (): SecurityNotification => notifPersonalSecurityNotification($this->admin);

    $this->notificationService->notifyUser($this->admin, $factory());

    $this->notificationService->markAllAsRead($this->admin);

    $this->notificationService->notifyUser($this->admin, $factory());

    expect($this->admin->notifications()->count())->toBe(2);
});

it('leaves no notification when the accounting transaction rolls back', function () {
    $invoice = notifPostSalesInvoice($this);

    // Re-posting the same invoice fails validation inside the transaction.
    try {
        (new SalesInvoicePostingService(new JournalEntryService))
            ->post($invoice->fresh(), $this->admin->id);
        $this->fail('Expected re-posting to fail.');
    } catch (InvalidArgumentException) {
        //
    }

    expect($this->accountant->notifications()->count())->toBe(1) // only the original posting
        ->and(DatabaseNotification::query()->where('data->dedup_key', "invoice_posted.{$invoice->id}")->count())->toBe(1);
});

it('never stores secrets in notification payloads', function () {
    notifPostSalesInvoice($this);

    $this->notificationService->notifyUser($this->admin, new SecurityNotification(
        title: 'Sécurité',
        message: 'Contrôle de confidentialité.',
        severity: NotificationSeverity::Info,
        dedupKey: 'personal.hygiene',
    ));

    foreach (DatabaseNotification::query()->get() as $row) {
        $encoded = strtolower(json_encode($row->data).''.$row->type.''.$row->id);

        foreach (['password', 'totp', 'recovery_code', 'secret', 'token', 'pgpassword'] as $needle) {
            expect(str_contains($encoded, $needle))->toBeFalse("Payload contains '{$needle}'");
        }
    }
});

// ---------------------------------------------------------------------------
// UI & access control
// ---------------------------------------------------------------------------

it('renders the notifications index page and dropdown for its owner', function () {
    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));

    actingAs($this->admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Test personnel');

    Livewire::actingAs($this->admin);

    Livewire::test(Dropdown::class)
        ->assertOk()
        ->assertSee('Test personnel')
        ->call('markAllAsRead');

    expect($this->admin->unreadNotifications()->count())->toBe(0);
});

it('blocks guests from the notifications pages', function () {
    get(route('notifications.index'))->assertRedirect(route('login'));
});

it('prevents marking another user notification as read', function () {
    $this->notificationService->notifyUser($this->admin, notifPersonalSecurityNotification($this->admin));

    $foreignId = (string) $this->admin->notifications()->firstOrFail()->id;

    // Another user's id resolves to nothing inside their own relation.
    expect($this->notificationService->markAsRead($this->accountant, $foreignId))->toBeFalse()
        ->and(DatabaseNotification::query()->whereKey($foreignId)->firstOrFail()->read_at)->toBeNull();
});
