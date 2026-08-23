<?php

use App\Enums\JournalEntryStatus;
use App\Livewire\Reports\IncomeStatement;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\IncomeStatementService;
use App\Services\Accounting\TrialBalanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

incomeCounter();

function incomeCounter(): int
{
    static $counter = 0;

    return ++$counter;
}

/**
 * Post a journal entry with the given lines: [[Account, debit, credit], ...].
 *
 * @param  array<array{0: Account, 1: string, 2: string}>  $lines
 */
function incomePostEntry($test, string $entryDate, array $lines, JournalEntryStatus $status = JournalEntryStatus::POSTED): JournalEntry
{
    $entry = JournalEntry::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', incomeCounter()),
        'entry_date' => $entryDate,
        'description' => 'Compte de résultat test entry',
        'status' => $status,
        'created_by' => $test->user->id,
        'posted_at' => $status === JournalEntryStatus::POSTED ? now() : null,
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
 * Post a posted revenue entry of the given amount through the bank account.
 */
function incomePostRevenue($test, string $entryDate, string $amount): void
{
    incomePostEntry($test, $entryDate, [
        [$test->bank, $amount, '0.000'],
        [$test->revenue, '0.000', $amount],
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->company = Company::create([
        'name' => 'Société Test',
        'legal_name' => 'Société Test SARL',
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

    // Only January is an open period on purpose: the income statement must
    // not depend on open periods for historical reporting windows.
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

    $makeAccount = fn (string $code, string $name, string $type, ?int $parentId = null) => Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $parentId,
        'code' => $code,
        'name' => $name,
        'account_type' => $type,
        'is_active' => true,
    ]);

    $this->bank = $makeAccount('512000', 'Banque', 'asset');
    $this->capital = $makeAccount('101000', 'Capital social', 'equity');
    $this->revenue = $makeAccount('707000', 'Ventes de marchandises', 'revenue');
    $this->otherRevenue = $makeAccount('701000', 'Ventes de produits finis', 'revenue');
    $this->expense = $makeAccount('613000', 'Locations et charges locatives', 'expense');
    $this->personnelExpense = $makeAccount('631000', 'Rémunérations du personnel', 'expense');
    $this->financialExpense = $makeAccount('661000', 'Charges d\'intérêts', 'expense');

    session(['current_company_id' => $this->company->id]);
    session(['current_fiscal_year_id' => $this->fiscalYear->id]);
});

function incomeService(): IncomeStatementService
{
    return app(IncomeStatementService::class);
}

// ---------- Controlled accounting scenarios ----------

it('computes the controlled profit scenario: produits 100000, charges 65000, résultat 35000', function () {
    // Revenue 100,000 credit; operating expense 60,000; financial expense 5,000.
    // The chart has no financial/exceptional category: all charges net together.
    incomePostEntry($this, '2026-03-10', [
        [$this->bank, '100000.000', '0.000'],
        [$this->revenue, '0.000', '100000.000'],
    ]);

    incomePostEntry($this, '2026-03-20', [
        [$this->expense, '60000.000', '0.000'],
        [$this->bank, '0.000', '60000.000'],
    ]);

    incomePostEntry($this, '2026-03-25', [
        [$this->financialExpense, '5000.000', '0.000'],
        [$this->bank, '0.000', '5000.000'],
    ]);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('100000.000')
        ->and($report['totals']['total_expenses'])->toBe('65000.000')
        ->and($report['totals']['net_result'])->toBe('35000.000')
        ->and($report['is_profit'])->toBeTrue()
        ->and($report['is_loss'])->toBeFalse();
});

it('computes the loss scenario: -15000 with is_loss flag', function () {
    incomePostEntry($this, '2026-04-01', [
        [$this->bank, '30000.000', '0.000'],
        [$this->revenue, '0.000', '30000.000'],
    ]);

    incomePostEntry($this, '2026-04-15', [
        [$this->expense, '45000.000', '0.000'],
        [$this->bank, '0.000', '45000.000'],
    ]);

    $service = incomeService();
    $report = $service->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $summary = $service->getSummary($report);

    expect($report['totals']['net_result'])->toBe('-15000.000')
        ->and($report['is_loss'])->toBeTrue()
        ->and($summary['has_loss'])->toBeTrue()
        ->and($summary['has_profit'])->toBeFalse()
        ->and($summary['is_break_even'])->toBeFalse();
});

it('reports a break-even result as neither profit nor loss', function () {
    incomePostRevenue($this, '2026-05-01', '10000.000');

    incomePostEntry($this, '2026-05-10', [
        [$this->expense, '10000.000', '0.000'],
        [$this->bank, '0.000', '10000.000'],
    ]);

    $service = incomeService();
    $report = $service->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $summary = $service->getSummary($report);

    expect($report['totals']['net_result'])->toBe('0.000')
        ->and($report['is_profit'])->toBeFalse()
        ->and($report['is_loss'])->toBeFalse()
        ->and($summary['is_break_even'])->toBeTrue();
});

// ---------- Entry status filtering ----------

it('includes posted revenue entries only', function () {
    incomePostRevenue($this, '2026-03-10', '1000.000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('1000.000');
});

it('excludes draft entries from the income statement', function () {
    incomePostEntry($this, '2026-03-10', [
        [$this->bank, '500.000', '0.000'],
        [$this->revenue, '0.000', '500.000'],
    ], JournalEntryStatus::DRAFT);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('0.000')
        ->and($report['revenues']->isEmpty())->toBeTrue();
});

it('excludes cancelled entries from the income statement', function () {
    incomePostEntry($this, '2026-03-10', [
        [$this->bank, '300.000', '0.000'],
        [$this->revenue, '0.000', '300.000'],
    ], JournalEntryStatus::CANCELLED);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('0.000')
        ->and($report['revenues']->isEmpty())->toBeTrue();
});

it('keeps only posted amounts when posted draft and cancelled coexist', function () {
    incomePostRevenue($this, '2026-02-10', '1000.000');

    incomePostEntry($this, '2026-02-11', [
        [$this->bank, '500.000', '0.000'],
        [$this->otherRevenue, '0.000', '500.000'],
    ], JournalEntryStatus::DRAFT);

    incomePostEntry($this, '2026-02-12', [
        [$this->bank, '300.000', '0.000'],
        [$this->otherRevenue, '0.000', '300.000'],
    ], JournalEntryStatus::CANCELLED);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('1000.000')
        ->and($report['totals']['total_expenses'])->toBe('0.000')
        ->and($report['totals']['net_result'])->toBe('1000.000');
});

// ---------- Sign normalization ----------

it('normalizes revenue amounts as credits minus debits including contra movements', function () {
    incomePostEntry($this, '2026-03-01', [
        [$this->bank, '8000.000', '0.000'],
        [$this->revenue, '0.000', '8000.000'],
    ]);

    // Contra movement: a client refund debited directly to the revenue account.
    incomePostEntry($this, '2026-03-15', [
        [$this->revenue, '2000.000', '0.000'],
        [$this->bank, '0.000', '2000.000'],
    ]);

    $rows = incomeService()->getRevenueAccounts($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $row = $rows->firstWhere('code', '707000');

    expect($row)->not->toBeNull()
        ->and($row['amount'])->toBe('6000.000');
});

it('keeps abnormal revenue balances visible as negative amounts instead of discarding them', function () {
    // Debit-only revenue account: abnormal balance, must stay negative.
    incomePostEntry($this, '2026-03-01', [
        [$this->revenue, '2500.000', '0.000'],
        [$this->bank, '0.000', '2500.000'],
    ]);

    $service = incomeService();
    $rows = $service->getRevenueAccounts($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $row = $rows->firstWhere('code', '707000');
    $report = $service->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($row)->not->toBeNull()
        ->and($row['amount'])->toBe('-2500.000')
        ->and($report['totals']['total_revenue'])->toBe('-2500.000');
});

it('normalizes expense amounts as debits minus credits including contra movements', function () {
    incomePostEntry($this, '2026-03-01', [
        [$this->expense, '9000.000', '0.000'],
        [$this->bank, '0.000', '9000.000'],
    ]);

    // Supplier credit note reimbursing part of the expense.
    incomePostEntry($this, '2026-03-20', [
        [$this->bank, '4000.000', '0.000'],
        [$this->expense, '0.000', '4000.000'],
    ]);

    $rows = incomeService()->getExpenseAccounts($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $row = $rows->firstWhere('code', '613000');

    expect($row)->not->toBeNull()
        ->and($row['amount'])->toBe('5000.000');
});

it('keeps abnormal expense balances visible as negative amounts instead of discarding them', function () {
    incomePostEntry($this, '2026-06-01', [
        [$this->bank, '1200.000', '0.000'],
        [$this->expense, '0.000', '1200.000'],
    ]);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $row = $report['expenses']->firstWhere('code', '613000');

    expect($row)->not->toBeNull()
        ->and($row['amount'])->toBe('-1200.000')
        ->and($report['totals']['total_expenses'])->toBe('-1200.000');
});

// ---------- Section and net totals ----------

it('sums total produits across several revenue accounts', function () {
    incomePostRevenue($this, '2026-02-01', '7000.000');

    incomePostEntry($this, '2026-02-05', [
        [$this->bank, '3000.000', '0.000'],
        [$this->otherRevenue, '0.000', '3000.000'],
    ]);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('10000.000');
});

it('sums total charges across operating personnel and financial expense accounts', function () {
    incomePostEntry($this, '2026-02-01', [
        [$this->expense, '2000.000', '0.000'],
        [$this->bank, '0.000', '2000.000'],
    ]);

    incomePostEntry($this, '2026-02-02', [
        [$this->personnelExpense, '5000.000', '0.000'],
        [$this->bank, '0.000', '5000.000'],
    ]);

    incomePostEntry($this, '2026-02-03', [
        [$this->financialExpense, '500.000', '0.000'],
        [$this->bank, '0.000', '500.000'],
    ]);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_expenses'])->toBe('7500.000')
        ->and($report['expenses']->count())->toBeGreaterThanOrEqual(3);
});

it('derives the net result from section totals consistently across helpers', function () {
    incomePostRevenue($this, '2026-03-01', '12000.000');

    incomePostEntry($this, '2026-03-05', [
        [$this->expense, '4500.000', '0.000'],
        [$this->bank, '0.000', '4500.000'],
    ]);

    $service = incomeService();
    $report = $service->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $totals = $service->calculateTotals($report['totals']['total_revenue'], $report['totals']['total_expenses']);

    expect($service->getOperatingResult('12000.000', '4500.000'))->toBe('7500.000')
        ->and($service->getNetResult('12000.000', '4500.000'))->toBe('7500.000')
        ->and($totals['net_result'])->toBe('7500.000')
        ->and($report['totals']['net_result'])->toBe('7500.000');
});

// ---------- Date boundaries ----------

it('includes an entry dated exactly on from_date', function () {
    incomePostRevenue($this, '2026-04-01', '400.000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-04-01', '2026-06-30');

    expect($report['totals']['total_revenue'])->toBe('400.000');
});

it('includes an entry dated exactly on to_date and excludes the day after', function () {
    incomePostRevenue($this, '2026-01-01', '100.000');
    incomePostRevenue($this, '2026-01-31', '200.000');
    incomePostRevenue($this, '2026-02-01', '500.000');

    $january = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-01-31');

    expect($january['totals']['total_revenue'])->toBe('300.000');

    $february = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-02-01', '2026-02-28');

    expect($february['totals']['total_revenue'])->toBe('500.000');
});

it('excludes movements before from_date and after to_date inside the fiscal year', function () {
    incomePostRevenue($this, '2026-01-15', '111.000');
    incomePostRevenue($this, '2026-02-01', '222.000');
    incomePostRevenue($this, '2026-02-28', '444.000');
    incomePostRevenue($this, '2026-03-01', '888.000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-02-01', '2026-02-28');

    expect($report['totals']['total_revenue'])->toBe('666.000');
});

it('supports historical mid-year windows even though periods are closed', function () {
    // Only January is an open period: June-to-September windows still work.
    incomePostRevenue($this, '2026-06-15', '4500.000');
    incomePostRevenue($this, '2026-09-30', '500.000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-06-01', '2026-09-30');

    expect($report['from_date'])->toBe('2026-06-01')
        ->and($report['to_date'])->toBe('2026-09-30')
        ->and($report['totals']['total_revenue'])->toBe('5000.000');
});

// ---------- Context validation ----------

it('rejects an invalid from date', function () {
    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, 'pas-une-date', '2026-12-31');
})->throws(InvalidArgumentException::class, 'La date de début est invalide.');

it('rejects an invalid to date', function () {
    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '31/12/2026');
})->throws(InvalidArgumentException::class, 'La date de fin est invalide.');

it('rejects dates outside the selected fiscal year', function () {
    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2025-12-01', '2026-12-31');
})->throws(InvalidArgumentException::class, 'Les dates du compte de résultat doivent être comprises entre le 01/01/2026 et le 31/12/2026.');

it('rejects a from date after the to date', function () {
    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-07-01', '2026-06-01');
})->throws(InvalidArgumentException::class, 'La date de début doit être antérieure ou égale à la date de fin.');

it('rejects a fiscal year belonging to another company', function () {
    $otherCompany = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);

    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    incomeService()->getIncomeStatement($this->company, $otherFy, '2026-01-01', '2026-12-31');
})->throws(InvalidArgumentException::class, 'L\'exercice n\'appartient pas à cette société.');

it('supports historical fiscal years independently of the current context', function () {
    $oldFy = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2025',
        'code' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
        'is_closed' => true,
    ]);

    $oldBank = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $oldFy->id,
        'code' => '512000',
        'name' => 'Banque 2025',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $oldRevenue = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $oldFy->id,
        'code' => '707000',
        'name' => 'Ventes 2025',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $oldFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', incomeCounter()),
        'entry_date' => '2025-11-20',
        'description' => 'Historical entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $oldBank->id,
        'debit' => '9000.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $oldRevenue->id,
        'debit' => '0.000',
        'credit' => '9000.000',
    ]);

    $historicalReport = incomeService()->getIncomeStatement($this->company, $oldFy, '2025-01-01', '2025-12-31');

    expect($historicalReport['totals']['total_revenue'])->toBe('9000.000')
        ->and($historicalReport['totals']['net_result'])->toBe('9000.000');

    // Current-year report remains untouched by prior-year data.
    $currentReport = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    expect($currentReport['totals']['total_revenue'])->toBe('0.000');
});

// ---------- Hierarchy and subtotals ----------

it('renders hierarchical revenue groups with rolled-up subtotals and prunes empty groups', function () {
    $root = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => '7',
        'name' => 'Produits',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $group = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $root->id,
        'code' => '70',
        'name' => 'Ventes de marchandises',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $leafA = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $group->id,
        'code' => '701001',
        'name' => 'Ventes locale',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $leafB = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $group->id,
        'code' => '707001',
        'name' => 'Ventes export',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $root->id,
        'code' => '76',
        'name' => 'Produits financiers (vide)',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    incomePostEntry($this, '2026-03-10', [
        [$this->bank, '4000.000', '0.000'],
        [$leafA, '0.000', '4000.000'],
    ]);

    incomePostEntry($this, '2026-03-11', [
        [$this->bank, '3000.000', '0.000'],
        [$leafB, '0.000', '3000.000'],
    ]);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $codes = $report['revenues']->pluck('code');

    expect($codes)->toContain('7')
        ->and($codes)->toContain('70')
        ->and($codes)->toContain('701001')
        ->and($codes)->toContain('707001')
        ->and($codes)->not->toContain('76')
        ->and($codes)->not->toContain('707000');

    $rootRow = $report['revenues']->firstWhere('code', '7');
    $groupRow = $report['revenues']->firstWhere('code', '70');
    $leafRow = $report['revenues']->firstWhere('code', '701001');

    expect($rootRow['depth'])->toBe(0)
        ->and($rootRow['is_group'])->toBeTrue()
        ->and($rootRow['subtotal'])->toBe('7000.000')
        ->and($rootRow['amount'])->toBe('0.000')
        ->and($groupRow['depth'])->toBe(1)
        ->and($groupRow['subtotal'])->toBe('7000.000')
        ->and($leafRow['depth'])->toBe(2)
        ->and($leafRow['is_group'])->toBeFalse()
        ->and($leafRow['amount'])->toBe('4000.000')
        ->and($report['totals']['total_revenue'])->toBe('7000.000');
});

// ---------- Zero-balance handling ----------

it('hides zero-movement accounts by default and shows them on request without changing totals', function () {
    incomePostRevenue($this, '2026-03-10', '2500.000');

    $hidden = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $shown = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31', true);

    expect($hidden['revenues']->pluck('code'))->toContain('707000')
        ->and($hidden['revenues']->pluck('code'))->not->toContain('701000')
        ->and($hidden['expenses']->pluck('code'))->not->toContain('613000')
        ->and($shown['revenues']->pluck('code'))->toContain('701000')
        ->and($shown['expenses']->pluck('code'))->toContain('613000')
        ->and($hidden['totals']['total_revenue'])->toBe($shown['totals']['total_revenue'])
        ->and($hidden['totals']['total_expenses'])->toBe($shown['totals']['total_expenses']);
});

// ---------- Read-only guarantee ----------

it('never writes to the database when generating the report', function () {
    incomePostRevenue($this, '2026-03-10', '6000.000');

    incomePostEntry($this, '2026-03-12', [
        [$this->expense, '2000.000', '0.000'],
        [$this->bank, '0.000', '2000.000'],
    ]);

    $countsBefore = [
        'entries' => JournalEntry::count(),
        'lines' => JournalEntryLine::count(),
        'accounts' => Account::count(),
        'companies' => Company::count(),
        'fiscal_years' => FiscalYear::count(),
    ];

    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    incomeService()->getRevenueAccounts($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    incomeService()->getExpenseAccounts($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    incomeService()->calculateTotals('1000.000', '500.000');
    incomeService()->getOperatingResult('1000.000', '500.000');
    incomeService()->getNetResult('1000.000', '500.000');

    expect(JournalEntry::count())->toBe($countsBefore['entries'])
        ->and(JournalEntryLine::count())->toBe($countsBefore['lines'])
        ->and(Account::count())->toBe($countsBefore['accounts'])
        ->and(Company::count())->toBe($countsBefore['companies'])
        ->and(FiscalYear::count())->toBe($countsBefore['fiscal_years']);
});

// ---------- Company isolation ----------

it('excludes cross-company data at service level', function () {
    $otherCompany = Company::create(['name' => 'Autre Société', 'currency' => 'TND', 'is_active' => true]);

    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $otherBank = Account::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'code' => '512999',
        'name' => 'Banque Autre Société',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', incomeCounter()),
        'entry_date' => '2026-04-04',
        'description' => 'Other company entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $otherBank->id,
        'debit' => '8888.000',
        'credit' => '0.000',
    ]);

    incomePostRevenue($this, '2026-04-04', '1111.000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    expect($report['totals']['total_revenue'])->toBe('1111.000')
        ->and($report['revenues']->pluck('code'))->not->toContain('512999');
});

it('isolates the multi-company scenario: company A sees 10000, company B sees 50000', function () {
    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $companyB->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $fyB = FiscalYear::create([
        'company_id' => $companyB->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $bankB = Account::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'code' => '512000',
        'name' => 'Banque B',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $revenueB = Account::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'code' => '707000',
        'name' => 'Ventes B',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $entryB = JournalEntry::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', incomeCounter()),
        'entry_date' => '2026-05-05',
        'description' => 'Company B entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entryB->id,
        'account_id' => $bankB->id,
        'debit' => '50000.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entryB->id,
        'account_id' => $revenueB->id,
        'debit' => '0.000',
        'credit' => '50000.000',
    ]);

    incomePostRevenue($this, '2026-05-05', '10000.000');

    // Company A report never sees company B amounts.
    $reportA = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    expect($reportA['totals']['total_revenue'])->toBe('10000.000');

    // Company B report never sees company A amounts.
    $reportB = incomeService()->getIncomeStatement($companyB, $fyB, '2026-01-01', '2026-12-31');
    expect($reportB['totals']['total_revenue'])->toBe('50000.000');
});

// ---------- Consistency with other reports ----------

it('reconciles with general ledger closing balances for the same window', function () {
    incomePostRevenue($this, '2026-03-01', '4200.000');

    incomePostEntry($this, '2026-03-05', [
        [$this->expense, '1700.000', '0.000'],
        [$this->bank, '0.000', '1700.000'],
    ]);

    $filters = [
        'from_date' => '2026-01-01',
        'to_date' => Carbon::parse('2026-12-31')->endOfDay()->format('Y-m-d H:i:s'),
    ];

    $revenueGl = (new GeneralLedgerService)->calculateClosingBalance($this->revenue, $this->company, $this->fiscalYear, $filters);
    $expenseGl = (new GeneralLedgerService)->calculateClosingBalance($this->expense, $this->company, $this->fiscalYear, $filters);

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    // GL nets are debit - credit: revenue shows negative there, positive here.
    expect(bcsub('0.000', $revenueGl, 3))->toBe($report['totals']['total_revenue'])
        ->and($revenueGl)->toBe('-4200.000')
        ->and($expenseGl)->toBe($report['totals']['total_expenses'])
        ->and($expenseGl)->toBe('1700.000');
});

it('reconciles with trial balance normalized balances for the same window', function () {
    incomePostRevenue($this, '2026-04-01', '3600.000');

    incomePostEntry($this, '2026-04-05', [
        [$this->expense, '2400.000', '0.000'],
        [$this->bank, '0.000', '2400.000'],
    ]);

    $trialBalance = (new TrialBalanceService)->getTrialBalance($this->company, $this->fiscalYear, [
        'from_date' => '2026-01-01',
        'to_date' => Carbon::parse('2026-12-31')->endOfDay()->format('Y-m-d H:i:s'),
        'include_zero_balance' => '1',
    ]);

    $tbRevenue = $trialBalance['accounts']->firstWhere('code', '707000');
    $tbExpense = $trialBalance['accounts']->firstWhere('code', '613000');

    $report = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $revenueRow = $report['revenues']->firstWhere('code', '707000');
    $expenseRow = $report['expenses']->firstWhere('code', '613000');

    // Trial balance splits the net into debit_balance / credit_balance sides;
    // the income statement re-normalizes each nature into one signed amount.
    expect($tbRevenue->credit_balance)->toBe($revenueRow['amount'])
        ->and($tbExpense->debit_balance)->toBe($expenseRow['amount'])
        ->and($report['totals']['total_revenue'])->toBe('3600.000')
        ->and($report['totals']['total_expenses'])->toBe('2400.000');
});

it('matches the balance sheet result for the same cumulative period', function () {
    incomePostRevenue($this, '2026-03-01', '40000.000');

    incomePostEntry($this, '2026-08-15', [
        [$this->expense, '25000.000', '0.000'],
        [$this->bank, '0.000', '25000.000'],
    ]);

    $incomeStatement = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');
    $balanceSheet = app(BalanceSheetService::class)->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($incomeStatement['totals']['net_result'])->toBe($balanceSheet['result']['value'])
        ->and($incomeStatement['totals']['total_revenue'])->toBe($balanceSheet['result']['revenue_total'])
        ->and($incomeStatement['totals']['total_expenses'])->toBe($balanceSheet['result']['expense_total']);
});

it('matches balance sheet calculateResult over the full fiscal year', function () {
    incomePostRevenue($this, '2026-02-01', '30000.000');

    incomePostEntry($this, '2026-09-10', [
        [$this->expense, '18000.000', '0.000'],
        [$this->bank, '0.000', '18000.000'],
    ]);

    incomePostEntry($this, '2026-09-20', [
        [$this->financialExpense, '2000.000', '0.000'],
        [$this->bank, '0.000', '2000.000'],
    ]);

    $incomeStatement = incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $balanceSheetService = app(BalanceSheetService::class);
    $balanceSheet = $balanceSheetService->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    $expected = $balanceSheetService->calculateResult(
        $balanceSheet['result']['revenue_total'],
        $balanceSheet['result']['expense_total'],
    );

    expect($balanceSheet['as_of_date'])->toBe('2026-12-31')
        ->and($incomeStatement['from_date'])->toBe('2026-01-01')
        ->and($incomeStatement['to_date'])->toBe('2026-12-31')
        ->and($incomeStatement['totals']['net_result'])->toBe($expected)
        ->and($incomeStatement['totals']['net_result'])->toBe('10000.000');
});

// ---------- Livewire page ----------

it('renders the income statement page with default period and French labels', function () {
    $this->actingAs($this->user);

    incomePostRevenue($this, '2026-03-10', '12345.000');

    Livewire::test(IncomeStatement::class)
        ->assertOk()
        ->assertSet('fromDate', '2026-01-01')
        ->assertSet('toDate', fn ($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1)
        ->assertSee('Compte de résultat')
        ->assertSee('Total Produits')
        ->assertSee('Total Charges')
        ->assertSee('Résultat net')
        ->assertSee('(Bénéfice)')
        ->assertSee('12 345,000');
});

it('shows the loss interpretation on the page', function () {
    $this->actingAs($this->user);

    incomePostRevenue($this, '2026-03-10', '30000.000');

    incomePostEntry($this, '2026-03-20', [
        [$this->expense, '45000.000', '0.000'],
        [$this->bank, '0.000', '45000.000'],
    ]);

    Livewire::test(IncomeStatement::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-12-31')
        ->assertOk()
        ->assertSee('(Perte)');
});

it('shows the break-even interpretation on the page', function () {
    $this->actingAs($this->user);

    incomePostRevenue($this, '2026-03-10', '8000.000');

    incomePostEntry($this, '2026-03-20', [
        [$this->expense, '8000.000', '0.000'],
        [$this->bank, '0.000', '8000.000'],
    ]);

    Livewire::test(IncomeStatement::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-12-31')
        ->assertOk()
        ->assertSee('(Résultat nul)');
});

it('switches the rendered report when the current company changes', function () {
    $this->actingAs($this->user);

    incomePostRevenue($this, '2026-03-10', '7777.000');

    $companyB = Company::create(['name' => 'Société B', 'currency' => 'TND', 'is_active' => true]);
    $companyB->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $fyB = FiscalYear::create([
        'company_id' => $companyB->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    session(['current_company_id' => $companyB->id, 'current_fiscal_year_id' => $fyB->id]);

    Livewire::test(IncomeStatement::class)
        ->assertOk()
        ->assertSee('Société B')
        ->assertDontSee('7 777,000');

    session(['current_company_id' => $this->company->id, 'current_fiscal_year_id' => $this->fiscalYear->id]);

    Livewire::test(IncomeStatement::class)
        ->assertOk()
        ->assertSee('7 777,000');
});

it('shows a clear error when the selected period leaves the fiscal year', function () {
    $this->actingAs($this->user);

    Livewire::test(IncomeStatement::class)
        ->set('fromDate', '2027-01-01')
        ->set('toDate', '2026-12-31')
        ->assertOk()
        ->assertSee('Les dates du compte de résultat doivent être comprises entre le 01/01/2026 et le 31/12/2026.');
});

it('shows a clear error when the period is inverted', function () {
    $this->actingAs($this->user);

    Livewire::test(IncomeStatement::class)
        ->set('fromDate', '2026-06-30')
        ->set('toDate', '2026-01-01')
        ->assertOk()
        ->assertSee('La date de début doit être antérieure ou égale à la date de fin.');
});

it('serves the income statement page through the authenticated route without company input', function () {
    $this->actingAs($this->user);

    incomePostRevenue($this, '2026-03-10', '6000.000');

    $response = $this->get(route('reports.income-statement'));

    $response->assertOk();
    expect(session('current_company_id'))->toBe($this->company->id);

    // Unauthenticated visitors are redirected to the login page.
    auth()->logout();

    $this->get(route('reports.income-statement'))->assertRedirect();
});

// ---------- Regression guards: existing reports remain intact ----------

it('leaves trial balance and general ledger results unchanged', function () {
    incomePostRevenue($this, '2026-03-10', '4200.000');

    $trialBalanceService = new TrialBalanceService;
    $before = $trialBalanceService->getTrialBalance($this->company, $this->fiscalYear);
    $glBefore = (new GeneralLedgerService)->calculateClosingBalance($this->bank, $this->company, $this->fiscalYear);

    incomeService()->getIncomeStatement($this->company, $this->fiscalYear, '2026-01-01', '2026-12-31');

    $after = $trialBalanceService->getTrialBalance($this->company, $this->fiscalYear);
    $glAfter = (new GeneralLedgerService)->calculateClosingBalance($this->bank, $this->company, $this->fiscalYear);

    expect($after['total_debit'])->toBe($before['total_debit'])
        ->and($after['total_credit'])->toBe($before['total_credit'])
        ->and($after['is_balanced'])->toBe($before['is_balanced'])
        ->and($after['is_balanced'])->toBeTrue()
        ->and($glAfter)->toBe($glBefore)
        ->and($glAfter)->toBe('4200.000');
});

it('keeps existing report routes intact alongside the new one', function () {
    foreach (['reports.general-ledger', 'reports.trial-balance', 'reports.balance-sheet', 'reports.customer-statement', 'reports.supplier-statement', 'reports.income-statement'] as $routeName) {
        expect(route($routeName))->toBeString();
    }
});
