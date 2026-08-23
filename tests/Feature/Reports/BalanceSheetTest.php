<?php

use App\Enums\JournalEntryStatus;
use App\Livewire\Reports\BalanceSheet;
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
use App\Services\Accounting\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

bsCounter();

function bsCounter(): int
{
    static $counter = 0;

    return ++$counter;
}

/**
 * Post a journal entry with the given lines: [[Account, debit, credit], ...].
 *
 * @param  array<array{0: Account, 1: string, 2: string}>  $lines
 */
function bsPostEntry($test, string $entryDate, array $lines, JournalEntryStatus $status = JournalEntryStatus::POSTED): JournalEntry
{
    $entry = JournalEntry::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'accounting_period_id' => $test->period->id,
        'journal_id' => $test->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', bsCounter()),
        'entry_date' => $entryDate,
        'description' => 'Bilan test entry',
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

    // Only January is an open period on purpose: the balance sheet must not
    // depend on the current accounting period for historical snapshot dates.
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
    $this->supplier = $makeAccount('401000', 'Fournisseurs', 'liability');
    $this->capital = $makeAccount('101000', 'Capital social', 'equity');
    $this->revenue = $makeAccount('707000', 'Ventes de marchandises', 'revenue');
    $this->expense = $makeAccount('613000', 'Locations', 'expense');

    session(['current_company_id' => $this->company->id]);
    session(['current_fiscal_year_id' => $this->fiscalYear->id]);
});

function bsService(): BalanceSheetService
{
    return app(BalanceSheetService::class);
}

// ---------- Controlled accounting scenario ----------

it('balances a controlled scenario: actif = passif + capitaux propres', function () {
    // Bank debit 10,000 = Supplier credit 4,000 + Capital credit 6,000.
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '10000.000', '0.000'],
        [$this->supplier, '0.000', '4000.000'],
        [$this->capital, '0.000', '6000.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['totals']['total_assets'])->toBe('10000.000')
        ->and($report['totals']['total_liabilities'])->toBe('4000.000')
        ->and($report['totals']['total_equity_accounts'])->toBe('6000.000')
        ->and($report['result']['value'])->toBe('0.000')
        ->and($report['totals']['total_equity_with_result'])->toBe('6000.000')
        ->and($report['totals']['total_passif_capitaux'])->toBe('10000.000')
        ->and($report['totals']['difference'])->toBe('0.000')
        ->and($report['is_balanced'])->toBeTrue();
});

it('calculates a profit result and stays balanced', function () {
    // Revenue 100,000 credited via bank; expense 60,000 debited via bank.
    bsPostEntry($this, '2026-02-01', [
        [$this->bank, '100000.000', '0.000'],
        [$this->revenue, '0.000', '100000.000'],
    ]);

    bsPostEntry($this, '2026-02-15', [
        [$this->expense, '60000.000', '0.000'],
        [$this->bank, '0.000', '60000.000'],
    ]);

    $service = bsService();
    $report = $service->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    $summary = $service->getSummary($report);

    expect($report['result']['revenue_total'])->toBe('100000.000')
        ->and($report['result']['expense_total'])->toBe('60000.000')
        ->and($report['result']['value'])->toBe('40000.000')
        ->and($summary['has_profit'])->toBeTrue()
        ->and($report['totals']['total_assets'])->toBe('40000.000')
        ->and($report['totals']['total_equity_with_result'])->toBe('40000.000')
        ->and($report['totals']['difference'])->toBe('0.000')
        ->and($report['is_balanced'])->toBeTrue();
});

it('calculates a loss result and represents contra asset balances negatively', function () {
    // Revenue 30,000; expenses 45,000 → loss of 15,000 and a negative bank balance.
    bsPostEntry($this, '2026-04-01', [
        [$this->bank, '30000.000', '0.000'],
        [$this->revenue, '0.000', '30000.000'],
    ]);

    bsPostEntry($this, '2026-04-15', [
        [$this->expense, '45000.000', '0.000'],
        [$this->bank, '0.000', '45000.000'],
    ]);

    $service = bsService();
    $report = $service->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    $summary = $service->getSummary($report);

    expect($report['result']['value'])->toBe('-15000.000')
        ->and($summary['has_profit'])->toBeFalse()
        ->and($report['totals']['total_assets'])->toBe('-15000.000')
        ->and($report['totals']['total_passif_capitaux'])->toBe('-15000.000')
        ->and($report['totals']['difference'])->toBe('0.000')
        ->and($report['is_balanced'])->toBeTrue();
});

// ---------- Entry status filtering ----------

it('includes posted entries in the balance sheet', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '5000.000', '0.000'],
        [$this->capital, '0.000', '5000.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['totals']['total_assets'])->toBe('5000.000');
});

it('excludes draft entries from the balance sheet', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '5000.000', '0.000'],
        [$this->capital, '0.000', '5000.000'],
    ], JournalEntryStatus::DRAFT);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['totals']['total_assets'])->toBe('0.000')
        ->and($report['assets']->isEmpty())->toBeTrue();
});

it('excludes cancelled entries from the balance sheet', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '5000.000', '0.000'],
        [$this->capital, '0.000', '5000.000'],
    ], JournalEntryStatus::CANCELLED);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['totals']['total_assets'])->toBe('0.000')
        ->and($report['assets']->isEmpty())->toBeTrue();
});

// ---------- Per-type balances ----------

it('calculates asset account balances correctly with net movements', function () {
    bsPostEntry($this, '2026-03-01', [
        [$this->bank, '8000.000', '0.000'],
        [$this->capital, '0.000', '8000.000'],
    ]);

    bsPostEntry($this, '2026-03-20', [
        [$this->expense, '1500.000', '0.000'],
        [$this->bank, '0.000', '1500.000'],
    ]);

    $rows = bsService()->getAssetAccounts($this->company, $this->fiscalYear, '2026-12-31');
    $bankRow = $rows->firstWhere('code', '512000');

    expect($bankRow)->not->toBeNull()
        ->and($bankRow['balance'])->toBe('6500.000');
});

it('calculates liability account balances correctly', function () {
    bsPostEntry($this, '2026-03-01', [
        [$this->expense, '1200.000', '0.000'],
        [$this->supplier, '0.000', '1200.000'],
    ]);

    bsPostEntry($this, '2026-03-25', [
        [$this->supplier, '700.000', '0.000'],
        [$this->bank, '0.000', '700.000'],
    ]);

    $rows = bsService()->getLiabilityAccounts($this->company, $this->fiscalYear, '2026-12-31');
    $supplierRow = $rows->firstWhere('code', '401000');

    expect($supplierRow)->not->toBeNull()
        ->and($supplierRow['balance'])->toBe('500.000');
});

it('calculates equity account balances correctly', function () {
    bsPostEntry($this, '2026-03-01', [
        [$this->bank, '9000.000', '0.000'],
        [$this->capital, '0.000', '9000.000'],
    ]);

    $rows = bsService()->getEquityAccounts($this->company, $this->fiscalYear, '2026-12-31');
    $capitalRow = $rows->firstWhere('code', '101000');

    expect($capitalRow)->not->toBeNull()
        ->and($capitalRow['balance'])->toBe('9000.000');
});

it('makes revenue and expense accounts contribute to the result', function () {
    bsPostEntry($this, '2026-05-01', [
        [$this->bank, '20000.000', '0.000'],
        [$this->revenue, '0.000', '20000.000'],
    ]);

    bsPostEntry($this, '2026-05-10', [
        [$this->expense, '12000.000', '0.000'],
        [$this->bank, '0.000', '12000.000'],
    ]);

    $service = bsService();
    $revenues = $service->getRevenueAccounts($this->company, $this->fiscalYear, '2026-12-31');
    $expenses = $service->getExpenseAccounts($this->company, $this->fiscalYear, '2026-12-31');

    $revenueRow = $revenues->firstWhere('code', '707000');
    $expenseRow = $expenses->firstWhere('code', '613000');

    expect($revenueRow['balance'])->toBe('20000.000')
        ->and($expenseRow['balance'])->toBe('12000.000')
        ->and($service->calculateResult('20000.000', '12000.000'))->toBe('8000.000');

    $report = $service->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    expect($report['result']['value'])->toBe('8000.000');
});

// ---------- Balance check ----------

it('detects and reports an unbalanced ledger instead of hiding it', function () {
    // Deliberately unbalanced manual data.
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '1000.000', '0.000'],
        [$this->capital, '0.000', '500.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['is_balanced'])->toBeFalse()
        ->and($report['totals']['difference'])->toBe('500.000');
});

// ---------- Date boundaries ----------

it('includes entries exactly on as_of_date and excludes later ones', function () {
    bsPostEntry($this, '2026-06-30', [
        [$this->bank, '3000.000', '0.000'],
        [$this->capital, '0.000', '3000.000'],
    ]);

    bsPostEntry($this, '2026-07-01', [
        [$this->bank, '2000.000', '0.000'],
        [$this->capital, '0.000', '2000.000'],
    ]);

    $atBoundary = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-06-30');
    $afterBoundary = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($atBoundary['totals']['total_assets'])->toBe('3000.000')
        ->and($afterBoundary['totals']['total_assets'])->toBe('5000.000');
});

it('excludes movements before as_of_date from revenue/expense result only when before fiscal year start is impossible', function () {
    // Cumulative logic: everything from FY start up to as_of_date counts.
    bsPostEntry($this, '2026-02-01', [
        [$this->bank, '5000.000', '0.000'],
        [$this->revenue, '0.000', '5000.000'],
    ]);

    bsPostEntry($this, '2026-09-01', [
        [$this->expense, '2000.000', '0.000'],
        [$this->bank, '0.000', '2000.000'],
    ]);

    // Snapshot at end of February: September expense not yet known.
    $febReport = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-02-28');

    expect($febReport['result']['value'])->toBe('5000.000')
        ->and($febReport['totals']['total_assets'])->toBe('5000.000');

    $decReport = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    expect($decReport['result']['value'])->toBe('3000.000');
});

it('allows historical dates inside closed periods without requiring the current open period', function () {
    // The only open period is January 2026 — a June date must still work.
    bsPostEntry($this, '2026-06-15', [
        [$this->bank, '4500.000', '0.000'],
        [$this->capital, '0.000', '4500.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-06-30');

    expect($report['as_of_date'])->toBe('2026-06-30')
        ->and($report['totals']['total_assets'])->toBe('4500.000');
});

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

    $oldCapital = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $oldFy->id,
        'code' => '101000',
        'name' => 'Capital 2025',
        'account_type' => 'equity',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $oldFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', bsCounter()),
        'entry_date' => '2025-11-20',
        'description' => 'Historical entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $oldBank->id,
        'debit' => '7000.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $oldCapital->id,
        'debit' => '0.000',
        'credit' => '7000.000',
    ]);

    $historicalReport = bsService()->getBalanceSheet($this->company, $oldFy, '2025-12-31');

    expect($historicalReport['totals']['total_assets'])->toBe('7000.000')
        ->and($historicalReport['is_balanced'])->toBeTrue();

    // Current-year report remains untouched by prior-year data.
    $currentReport = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    expect($currentReport['totals']['total_assets'])->toBe('0.000');
});

// ---------- Context validation ----------

it('rejects invalid as-of dates', function () {
    bsService()->getBalanceSheet($this->company, $this->fiscalYear, 'pas-une-date');
})->throws(InvalidArgumentException::class);

it('rejects as-of dates outside the selected fiscal year', function () {
    bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2027-05-01');
})->throws(InvalidArgumentException::class, 'La date du bilan doit être comprise entre le 01/01/2026 et le 31/12/2026.');

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

    bsService()->getBalanceSheet($this->company, $otherFy, '2026-12-31');
})->throws(InvalidArgumentException::class, 'L\'exercice n\'appartient pas à cette société.');

// ---------- Zero-balance handling ----------

it('hides zero-balance accounts by default and shows them on request', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '2500.000', '0.000'],
        [$this->capital, '0.000', '2500.000'],
    ]);

    $hidden = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    $codesHidden = $hidden['assets']->pluck('code');

    expect($codesHidden)->toContain('512000')
        ->and($codesHidden)->not->toContain('401000');

    $shown = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31', true);

    expect($shown['assets']->pluck('code'))->toContain('512000')
        ->and($hidden['totals']['total_assets'])->toBe($shown['totals']['total_assets']);
});

it('prunes empty groups while keeping groups with non-zero subtotals', function () {
    $immobilisations = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => '2',
        'name' => 'Immobilisations',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $materiel = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $immobilisations->id,
        'code' => '24',
        'name' => 'Matériel',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => '3',
        'name' => 'Stocks (vide)',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    bsPostEntry($this, '2026-03-10', [
        [$materiel, '7500.000', '0.000'],
        [$this->capital, '0.000', '7500.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    $codes = $report['assets']->pluck('code');

    expect($codes)->toContain('2')
        ->and($codes)->toContain('24')
        ->and($codes)->not->toContain('3')
        ->and($codes)->not->toContain('512000');

    $root = $report['assets']->firstWhere('code', '2');
    $child = $report['assets']->firstWhere('code', '24');

    expect($root['depth'])->toBe(0)
        ->and($root['is_group'])->toBeTrue()
        ->and($root['subtotal'])->toBe('7500.000')
        ->and($root['balance'])->toBe('0.000')
        ->and($child['depth'])->toBe(1)
        ->and($child['is_group'])->toBeFalse()
        ->and($child['balance'])->toBe('7500.000')
        ->and($report['totals']['total_assets'])->toBe('7500.000');
});

// ---------- Mixed-type hierarchy (class 4 Tiers) ----------

it('re-parents accounts to their nearest same-type ancestor in mixed subtrees', function () {
    $tiers = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => null,
        'code' => '4',
        'name' => 'Tiers',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $etat = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $tiers->id,
        'code' => '44',
        'name' => 'État',
        'account_type' => 'liability',
        'is_active' => true,
    ]);

    $tvaDeductible = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'parent_id' => $etat->id,
        'code' => '444',
        'name' => 'TVA déductible',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    bsPostEntry($this, '2026-03-10', [
        [$tvaDeductible, '190.000', '0.000'],
        [$etat, '0.000', '190.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    // Asset side: 444 hangs under root "4" (nearest asset ancestor), not under liability "44".
    $assetRoot = $report['assets']->firstWhere('code', '4');
    $tvaRow = $report['assets']->firstWhere('code', '444');

    expect($assetRoot)->not->toBeNull()
        ->and($assetRoot['subtotal'])->toBe('190.000')
        ->and($tvaRow)->not->toBeNull()
        ->and($tvaRow['depth'])->toBe(1)
        ->and($report['totals']['total_assets'])->toBe('190.000');

    // Liability side: 44 becomes its own root since parent "4" is an asset.
    $etatRow = $report['liabilities']->firstWhere('code', '44');

    expect($etatRow)->not->toBeNull()
        ->and($etatRow['depth'])->toBe(0)
        ->and($etatRow['subtotal'])->toBe('190.000')
        ->and($report['totals']['total_liabilities'])->toBe('190.000');
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
        'entry_number' => sprintf('OD-TEST-%06d', bsCounter()),
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

    bsPostEntry($this, '2026-04-04', [
        [$this->bank, '1111.000', '0.000'],
        [$this->capital, '0.000', '1111.000'],
    ]);

    $report = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    expect($report['totals']['total_assets'])->toBe('1111.000')
        ->and($report['assets']->pluck('code'))->not->toContain('512999');
});

it('isolates companies in the multi-company scenario', function () {
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

    $capitalB = Account::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'code' => '101000',
        'name' => 'Capital B',
        'account_type' => 'equity',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => sprintf('OD-TEST-%06d', bsCounter()),
        'entry_date' => '2026-05-05',
        'description' => 'Company B entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $bankB->id,
        'debit' => '22222.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $capitalB->id,
        'debit' => '0.000',
        'credit' => '22222.000',
    ]);

    bsPostEntry($this, '2026-05-05', [
        [$this->bank, '3333.000', '0.000'],
        [$this->capital, '0.000', '3333.000'],
    ]);

    // Company A report never sees company B amounts.
    $reportA = bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    expect($reportA['totals']['total_assets'])->toBe('3333.000');

    // Company B report never sees company A amounts.
    $reportB = bsService()->getBalanceSheet($companyB, $fyB, '2026-12-31');
    expect($reportB['totals']['total_assets'])->toBe('22222.000');
});

// ---------- Read-only guarantee ----------

it('never writes to the database when generating the report', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '6000.000', '0.000'],
        [$this->supplier, '0.000', '2500.000'],
        [$this->capital, '0.000', '3500.000'],
    ]);

    $countsBefore = [
        'entries' => JournalEntry::count(),
        'lines' => JournalEntryLine::count(),
        'accounts' => Account::count(),
        'companies' => Company::count(),
        'fiscal_years' => FiscalYear::count(),
    ];

    bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');
    bsService()->getAssetAccounts($this->company, $this->fiscalYear, '2026-12-31');
    bsService()->getLiabilityAccounts($this->company, $this->fiscalYear, '2026-12-31');
    bsService()->getEquityAccounts($this->company, $this->fiscalYear, '2026-12-31');

    expect(JournalEntry::count())->toBe($countsBefore['entries'])
        ->and(JournalEntryLine::count())->toBe($countsBefore['lines'])
        ->and(Account::count())->toBe($countsBefore['accounts'])
        ->and(Company::count())->toBe($countsBefore['companies'])
        ->and(FiscalYear::count())->toBe($countsBefore['fiscal_years']);
});

// ---------- Livewire page ----------

it('renders the balance sheet page for the current company', function () {
    $this->actingAs($this->user);

    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '12345.000', '0.000'],
        [$this->supplier, '0.000', '2345.000'],
        [$this->capital, '0.000', '10000.000'],
    ]);

    Livewire::test(BalanceSheet::class)
        ->assertOk()
        ->assertSet('asOfDate', fn ($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1)
        ->assertSee('Bilan')
        ->assertSee('Total Actif')
        ->assertSee('Le bilan est équilibré')
        ->assertSee('12 345,000');
});

it('switches the rendered report when the current company changes', function () {
    $this->actingAs($this->user);

    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '7777.000', '0.000'],
        [$this->capital, '0.000', '7777.000'],
    ]);

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

    Livewire::test(BalanceSheet::class)
        ->assertOk()
        ->assertSee('Société B')
        ->assertDontSee('7 777,000');

    session(['current_company_id' => $this->company->id, 'current_fiscal_year_id' => $this->fiscalYear->id]);

    Livewire::test(BalanceSheet::class)
        ->assertOk()
        ->assertSee('7 777,000');
});

it('shows a clear error when the selected date leaves the fiscal year', function () {
    $this->actingAs($this->user);

    Livewire::test(BalanceSheet::class)
        ->set('asOfDate', '2027-03-01')
        ->assertOk()
        ->assertSee('La date du bilan doit être comprise entre le 01/01/2026 et le 31/12/2026.');
});

it('shows the imbalance warning on the page for unbalanced data', function () {
    $this->actingAs($this->user);

    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '900.000', '0.000'],
        [$this->capital, '0.000', '400.000'],
    ]);

    Livewire::test(BalanceSheet::class)
        ->assertOk()
        ->assertSee('Le bilan présente un écart de');
});

// ---------- Regression guards: existing reports remain intact ----------

it('leaves trial balance and general ledger results unchanged', function () {
    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '4200.000', '0.000'],
        [$this->revenue, '0.000', '4200.000'],
    ]);

    $trialBalanceService = new TrialBalanceService;
    $before = $trialBalanceService->getTrialBalance($this->company, $this->fiscalYear);
    $glBefore = (new GeneralLedgerService)->calculateClosingBalance($this->bank, $this->company, $this->fiscalYear);

    bsService()->getBalanceSheet($this->company, $this->fiscalYear, '2026-12-31');

    $after = $trialBalanceService->getTrialBalance($this->company, $this->fiscalYear);
    $glAfter = (new GeneralLedgerService)->calculateClosingBalance($this->bank, $this->company, $this->fiscalYear);

    expect($after['total_debit'])->toBe($before['total_debit'])
        ->and($after['total_credit'])->toBe($before['total_credit'])
        ->and($after['is_balanced'])->toBe($before['is_balanced'])
        ->and($after['is_balanced'])->toBeTrue()
        ->and($glAfter)->toBe($glBefore)
        ->and($glAfter)->toBe('4200.000');
});

it('keeps existing report routes intact', function () {
    foreach (['reports.general-ledger', 'reports.trial-balance', 'reports.customer-statement', 'reports.supplier-statement'] as $routeName) {
        expect(route($routeName))->toBeString();
    }
});

it('serves the balance sheet page through the authenticated route without company input', function () {
    $this->actingAs($this->user);

    bsPostEntry($this, '2026-03-10', [
        [$this->bank, '6000.000', '0.000'],
        [$this->capital, '0.000', '6000.000'],
    ]);

    $response = $this->get(route('reports.balance-sheet'));

    $response->assertOk();
    expect(session('current_company_id'))->toBe($this->company->id);

    // Unauthenticated visitors are redirected to the login page.
    auth()->logout();

    $this->get(route('reports.balance-sheet'))->assertRedirect();
});
