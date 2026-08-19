<?php

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->email_verified_at = now();
    $this->user->save();

    $this->company = Company::create([
        'name' => 'Test Company',
        'legal_name' => 'Test Company SARL',
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

    $this->account411 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '411000',
        'name' => 'Clients',
        'account_type' => 'asset',
        'is_active' => true,
    ]);

    $this->account707 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '707000',
        'name' => 'Ventes',
        'account_type' => 'revenue',
        'is_active' => true,
    ]);

    $this->account4457 = Account::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => '445700',
        'name' => 'TVA collectée',
        'account_type' => 'liability',
        'is_active' => true,
    ]);
});

it('includes posted entries in general ledger', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Test entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1190.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account707->id,
        'debit' => '0.000',
        'credit' => '1000.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account4457->id,
        'debit' => '0.000',
        'credit' => '190.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear);

    expect($lines)->toHaveCount(1);
    expect($lines->first()->debit)->toBe('1190.000');
});

it('excludes draft entries from general ledger', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Draft entry',
        'status' => JournalEntryStatus::DRAFT,
        'created_by' => $this->user->id,
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear);

    expect($lines)->toHaveCount(0);
});

it('excludes cancelled entries from general ledger', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Cancelled entry',
        'status' => JournalEntryStatus::CANCELLED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear);

    expect($lines)->toHaveCount(0);
});

it('calculates correct running balance in general ledger', function () {
    $entry1 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-10',
        'description' => 'First entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry1->id,
        'account_id' => $this->account411->id,
        'debit' => '1190.000',
        'credit' => '0.000',
    ]);

    $entry2 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000002',
        'entry_date' => '2026-01-15',
        'description' => 'Second entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry2->id,
        'account_id' => $this->account411->id,
        'debit' => '0.000',
        'credit' => '500.000',
    ]);

    $service = new GeneralLedgerService;
    $summary = $service->getLedgerSummary($this->account411, $this->company, $this->fiscalYear);

    expect($summary['opening_balance'])->toBe('0.000');
    expect($summary['closing_balance'])->toBe('690.000');
});

it('calculates opening balance when from_date is provided', function () {
    $entry1 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-05',
        'description' => 'Before from_date',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry1->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $entry2 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000002',
        'entry_date' => '2026-01-15',
        'description' => 'After from_date',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry2->id,
        'account_id' => $this->account411->id,
        'debit' => '500.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $opening = $service->calculateOpeningBalance($this->account411, $this->company, $this->fiscalYear, '2026-01-10');

    expect($opening)->toBe('1000.000');
});

it('filters general ledger by date range', function () {
    $entry1 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-05',
        'description' => 'Outside range',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry1->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $entry2 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000002',
        'entry_date' => '2026-01-15',
        'description' => 'Inside range',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry2->id,
        'account_id' => $this->account411->id,
        'debit' => '500.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear, [
        'from_date' => '2026-01-10',
        'to_date' => '2026-01-20',
    ]);

    expect($lines)->toHaveCount(1);
    expect($lines->first()->entry_date)->toContain('2026-01-15');
});

it('filters general ledger by journal', function () {
    $otherJournal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'code' => 'VE',
        'name' => 'Ventes',
        'type' => 'ventes',
        'is_active' => true,
    ]);

    $entry1 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-10',
        'description' => 'OD entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry1->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $entry2 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $otherJournal->id,
        'entry_number' => 'VE-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'VE entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry2->id,
        'account_id' => $this->account411->id,
        'debit' => '500.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear, [
        'journal_id' => $otherJournal->id,
    ]);

    expect($lines)->toHaveCount(1);
    expect($lines->first()->journal_code)->toBe('VE');
});

it('calculates correct trial balance totals', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Test entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1190.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account707->id,
        'debit' => '0.000',
        'credit' => '1000.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account4457->id,
        'debit' => '0.000',
        'credit' => '190.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear);

    expect($result['total_debit'])->toBe('1190.000');
    expect($result['total_credit'])->toBe('1190.000');
    expect($result['is_balanced'])->toBeTrue();
});

it('excludes draft entries from trial balance', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Draft entry',
        'status' => JournalEntryStatus::DRAFT,
        'created_by' => $this->user->id,
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear);

    expect($result['total_debit'])->toBe('0.000');
    expect($result['total_credit'])->toBe('0.000');
    expect($result['accounts'])->toHaveCount(0);
});

it('excludes cancelled entries from trial balance', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Cancelled entry',
        'status' => JournalEntryStatus::CANCELLED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear);

    expect($result['total_debit'])->toBe('0.000');
});

it('calculates correct debit and credit balances in trial balance', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Test entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1190.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account707->id,
        'debit' => '0.000',
        'credit' => '1000.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear);

    $account411 = $result['accounts']->firstWhere('code', '411000');
    $account707 = $result['accounts']->firstWhere('code', '707000');

    expect($account411->debit_balance)->toBe('1190.000');
    expect($account411->credit_balance)->toBe('0.000');
    expect($account707->debit_balance)->toBe('0.000');
    expect($account707->credit_balance)->toBe('1000.000');
});

it('filters trial balance by date range', function () {
    $entry1 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-05',
        'description' => 'Before range',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry1->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $entry2 = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000002',
        'entry_date' => '2026-01-15',
        'description' => 'Inside range',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry2->id,
        'account_id' => $this->account411->id,
        'debit' => '500.000',
        'credit' => '0.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear, [
        'from_date' => '2026-01-10',
        'to_date' => '2026-01-20',
    ]);

    expect($result['total_debit'])->toBe('500.000');
});

it('filters trial balance by account type', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Test entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account707->id,
        'debit' => '0.000',
        'credit' => '1000.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear, [
        'account_type' => 'asset',
    ]);

    expect($result['accounts'])->toHaveCount(1);
    expect($result['accounts']->first()->code)->toBe('411000');
});

it('includes zero-balance accounts when requested', function () {
    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Test entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear, [
        'include_zero_balance' => '1',
    ]);

    expect($result['accounts']->count())->toBeGreaterThanOrEqual(3);
});

it('blocks cross-company data in general ledger', function () {
    $otherCompany = Company::create([
        'name' => 'Other Company',
        'currency' => 'TND',
        'is_active' => true,
    ]);
    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Other company entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear);

    expect($lines)->toHaveCount(0);
});

it('blocks cross-company data in trial balance', function () {
    $otherCompany = Company::create([
        'name' => 'Other Company',
        'currency' => 'TND',
        'is_active' => true,
    ]);
    $otherCompany->users()->attach($this->user, ['role' => 'admin', 'is_active' => true]);

    $otherFy = FiscalYear::create([
        'company_id' => $otherCompany->id,
        'name' => 'Exercice 2026',
        'code' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Other company entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new TrialBalanceService;
    $result = $service->getTrialBalance($this->company, $this->fiscalYear);

    expect($result['total_debit'])->toBe('0.000');
});

it('blocks cross-fiscal-year data in general ledger', function () {
    $otherFy = FiscalYear::create([
        'company_id' => $this->company->id,
        'name' => 'Exercice 2025',
        'code' => '2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
    ]);

    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $otherFy->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2025-000001',
        'entry_date' => '2025-06-15',
        'description' => 'Other FY entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $lines = $service->getAccountLedger($this->account411, $this->company, $this->fiscalYear);

    expect($lines)->toHaveCount(0);
});

it('displays inactive accounts with historical data in general ledger', function () {
    $this->account411->update(['is_active' => false]);

    $entry = JournalEntry::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'accounting_period_id' => $this->period->id,
        'journal_id' => $this->journal->id,
        'entry_number' => 'OD-2026-000001',
        'entry_date' => '2026-01-15',
        'description' => 'Historical entry',
        'status' => JournalEntryStatus::POSTED,
        'created_by' => $this->user->id,
        'posted_at' => now(),
    ]);

    JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $this->account411->id,
        'debit' => '1000.000',
        'credit' => '0.000',
    ]);

    $service = new GeneralLedgerService;
    $accounts = $service->getAccountsForContext($this->company, $this->fiscalYear);

    expect($accounts->pluck('code'))->toContain('411000');
});
