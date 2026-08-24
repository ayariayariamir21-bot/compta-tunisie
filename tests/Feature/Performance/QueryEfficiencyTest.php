<?php

use App\Enums\JournalEntryStatus;
use App\Enums\NotificationSeverity;
use App\Livewire\Notifications\Dropdown;
use App\Livewire\Reports\GeneralLedger;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Notifications\SecurityNotification;
use App\Services\Accounting\CustomerStatementService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Security\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->company = Company::create(['name' => 'Perf Co', 'currency' => 'TND', 'is_active' => true]);
    $this->company->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $this->fiscalYear = FiscalYear::create([
        'company_id' => $this->company->id, 'name' => 'FY2026', 'code' => '2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'is_active' => true, 'is_closed' => false,
    ]);

    $this->period = AccountingPeriod::create([
        'fiscal_year_id' => $this->fiscalYear->id, 'name' => 'Jan', 'code' => '2026-01',
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
    ]);

    $this->journal = Journal::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'VTE', 'name' => 'Ventes', 'type' => 'ventes',
    ]);

    session([
        'current_company_id' => $this->company->id,
        'current_fiscal_year_id' => $this->fiscalYear->id,
    ]);
});

/**
 * Seed $count accounts with one posted entry (two lines) each.
 */
function seedAccountsWithEntries($test, int $count): void
{
    foreach (range(1, $count) as $i) {
        $account = Account::create([
            'company_id' => $test->company->id,
            'fiscal_year_id' => $test->fiscalYear->id,
            'code' => sprintf('70%04d', $i),
            'name' => "Produit {$i}",
            'account_type' => 'revenue',
            'is_active' => true,
        ]);

        $entry = JournalEntry::create([
            'company_id' => $test->company->id,
            'fiscal_year_id' => $test->fiscalYear->id,
            'accounting_period_id' => $test->period->id,
            'journal_id' => $test->journal->id,
            'entry_number' => sprintf('E-%06d', $i),
            'entry_date' => '2026-01-10',
            'status' => JournalEntryStatus::POSTED->value,
            'created_by' => $test->user->id,
        ]);

        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'debit' => '0.000', 'credit' => '50.000']);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $account->id, 'debit' => '50.000', 'credit' => '0.000']);
    }
}

function countQueries(callable $fn): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    $fn();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('keeps the general ledger report bounded in queries as accounts grow', function () {
    seedAccountsWithEntries($this, 12);

    $service = app(GeneralLedgerService::class);
    actingAs($this->user);

    $queries = countQueries(function (): void {
        Livewire::test(GeneralLedger::class);
    });

    // Accounts + journals + lines + openings + context; must NOT grow by
    // two queries per account (would be 30+ with 12 accounts).
    expect($queries)->toBeLessThan(16);
});

it('produces batched ledger summaries identical to per-account summaries', function () {
    seedAccountsWithEntries($this, 6);

    $service = app(GeneralLedgerService::class);
    $accounts = $service->getAccountsForContext($this->company, $this->fiscalYear);
    $filters = ['from_date' => '2026-01-01', 'to_date' => '2026-12-31'];

    $batched = $service->getLedgerSummaries($accounts, $this->company, $this->fiscalYear, $filters);

    foreach ($accounts as $account) {
        $single = $service->getLedgerSummary($account, $this->company, $this->fiscalYear, $filters);
        $batch = $batched[$account->id];

        expect($batch['opening_balance'])->toBe($single['opening_balance'])
            ->and($batch['closing_balance'])->toBe($single['closing_balance'])
            ->and($batch['lines']->count())->toBe($single['lines']->count())
            ->and($batch['lines']->pluck('debit')->all())->toBe($single['lines']->pluck('debit')->all())
            ->and($batch['lines']->pluck('credit')->all())->toBe($single['lines']->pluck('credit')->all());
    }
});

it('keeps the accounts page free of per-row lazy loads', function () {
    seedAccountsWithEntries($this, 20);

    actingAs($this->user);

    $queries = countQueries(fn (): TestResponse => test()->get(route('accounts.index')));

    // Would exceed 40 with a fiscal-year lazy load and children EXISTS per row.
    expect($queries)->toBeLessThan(20);
});

it('keeps statement generation bounded in queries', function () {
    seedAccountsWithEntries($this, 3);

    $customer = Customer::create([
        'company_id' => $this->company->id, 'code' => 'CL001',
        'name' => 'Client Test', 'customer_type' => 'individual', 'country' => 'TN',
        'payment_terms_days' => 30, 'is_active' => true,
    ]);

    $service = app(CustomerStatementService::class);

    $queries = countQueries(fn () => $service->getStatement($customer, null, null));

    expect($queries)->toBeLessThan(8);
});

it('keeps the notification dropdown bounded in queries', function () {
    actingAs($this->user);

    $service = app(NotificationService::class);

    foreach (range(1, 12) as $i) {
        $service->notifyUser($this->user, new SecurityNotification(
            title: "Notif {$i}",
            message: 'Contenu',
            severity: NotificationSeverity::Info,
            dedupKey: "perf.dropdown.{$i}",
        ));
    }

    $queries = countQueries(fn () => Livewire::test(Dropdown::class));

    // Latest-8 window plus unread count; must not scale with total rows.
    expect($queries)->toBeLessThan(8);
});
