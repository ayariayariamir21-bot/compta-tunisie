<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Normalize a value to a numeric string with 3 decimal places.
     *
     *
     * @return numeric-string
     */
    private function normalizeDecimal(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        $numVal = is_numeric($value) ? (string) $value : '0.000';

        return number_format((float) $numVal, 3, '.', '');
    }

    /**
     * Get all accounts that have posted journal entry lines in the current context.
     *
     * Includes inactive accounts with historical data.
     *
     * @return Collection<int, Account>
     */
    public function getAccountsForContext(Company $company, FiscalYear $fiscalYear): EloquentCollection
    {
        return Account::where('accounts.company_id', $company->id)
            ->where('accounts.fiscal_year_id', $fiscalYear->id)
            ->whereExists(function ($query) use ($company, $fiscalYear) {
                $query->select(DB::raw(1))
                    ->from('journal_entry_lines')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->whereRaw('journal_entry_lines.account_id = accounts.id')
                    ->where('journal_entries.company_id', $company->id)
                    ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
                    ->where('journal_entries.status', JournalEntryStatus::POSTED);
            })
            ->orderBy('accounts.code')
            ->get();
    }

    /**
     * Get ledger lines for a specific account within the current context.
     *
     * Ordering: entry_date ASC, journal code ASC, entry_number ASC, line id ASC.
     *
     * @param  array{from_date?: string, to_date?: string, journal_id?: int|null, search?: string}  $filters
     * @return Collection<int, JournalEntryLine>
     */
    public function getAccountLedger(Account $account, Company $company, FiscalYear $fiscalYear, array $filters = []): EloquentCollection
    {
        return JournalEntryLine::query()
            ->select([
                'journal_entry_lines.id',
                'journal_entry_lines.description as line_description',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entries.entry_date',
                'journal_entries.entry_number',
                'journal_entries.reference',
                'journal_entries.description as entry_description',
                'journals.code as journal_code',
                'journals.name as journal_name',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED)
            ->when(isset($filters['from_date']) && $filters['from_date'] !== '', function ($q) use ($filters) {
                $fromDate = $filters['from_date'] ?? '';
                $q->where('journal_entries.entry_date', '>=', $fromDate);
            })
            ->when(isset($filters['to_date']) && $filters['to_date'] !== '', function ($q) use ($filters) {
                $toDate = $filters['to_date'] ?? '';
                $q->where('journal_entries.entry_date', '<=', $toDate);
            })
            ->when(isset($filters['journal_id']), function ($q) use ($filters) {
                $q->where('journal_entries.journal_id', $filters['journal_id']);
            })
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'] ?? '';
                $q->where(function ($q2) use ($search) {
                    $q2->where('journal_entries.reference', 'like', "%{$search}%")
                        ->orWhere('journal_entries.description', 'like', "%{$search}%")
                        ->orWhere('journal_entry_lines.description', 'like', "%{$search}%");
                });
            })
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journals.code')
            ->orderBy('journal_entries.entry_number')
            ->orderBy('journal_entry_lines.id')
            ->get();
    }

    /**
     * Calculate the opening balance for an account before the given from_date.
     *
     * Returns the sum of all debit and credit from posted entries before from_date.
     * If no from_date is provided, opening balance is 0.
     *
     * @return numeric-string
     */
    public function calculateOpeningBalance(Account $account, Company $company, FiscalYear $fiscalYear, ?string $fromDate = null): string
    {
        if ($fromDate === null || $fromDate === '') {
            return '0.000';
        }

        $result = JournalEntryLine::query()
            ->select([
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED)
            ->where('journal_entries.entry_date', '<', $fromDate)
            ->first();

        $debit = number_format((float) ($result->total_debit ?? 0), 3, '.', '');
        $credit = number_format((float) ($result->total_credit ?? 0), 3, '.', '');

        return bcsub($debit, $credit, 3);
    }

    /**
     * Safe bcadd that guarantees numeric-string return.
     *
     * @param  numeric-string  $num1
     * @param  numeric-string  $num2
     * @return numeric-string
     */
    private function safeBcadd(string $num1, string $num2, int $scale = 3): string
    {
        return bcadd($num1, $num2, $scale);
    }

    /**
     * Safe bcsub that guarantees numeric-string return.
     *
     * @param  numeric-string  $num1
     * @param  numeric-string  $num2
     * @return numeric-string
     */
    private function safeBcsub(string $num1, string $num2, int $scale = 3): string
    {
        return bcsub($num1, $num2, $scale);
    }

    /**
     * Calculate the closing balance for an account.
     *
     * @param  array{from_date?: string, to_date?: string, journal_id?: int|null, search?: string}  $filters
     * @return numeric-string
     */
    public function calculateClosingBalance(Account $account, Company $company, FiscalYear $fiscalYear, array $filters = []): string
    {
        $query = JournalEntryLine::query()
            ->select([
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED);

        if (isset($filters['from_date']) && $filters['from_date'] !== '') {
            $query->where('journal_entries.entry_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date']) && $filters['to_date'] !== '') {
            $query->where('journal_entries.entry_date', '<=', $filters['to_date']);
        }

        if (isset($filters['journal_id'])) {
            $query->where('journal_entries.journal_id', $filters['journal_id']);
        }

        $result = $query->first();

        $debit = number_format((float) ($result->total_debit ?? 0), 3, '.', '');
        $credit = number_format((float) ($result->total_credit ?? 0), 3, '.', '');

        return bcsub($debit, $credit, 3);
    }

    /**
     * Get the full ledger summary for an account including opening balance, lines, and closing balance.
     *
     * @param  array{from_date?: string, to_date?: string, journal_id?: int|null, search?: string}  $filters
     * @return array{account: Account, opening_balance: numeric-string, lines: Collection<int, JournalEntryLine>, closing_balance: numeric-string}
     */
    public function getLedgerSummary(Account $account, Company $company, FiscalYear $fiscalYear, array $filters = []): array
    {
        $lines = $this->getAccountLedger($account, $company, $fiscalYear, $filters);
        $openingBalance = $this->calculateOpeningBalance($account, $company, $fiscalYear, $filters['from_date'] ?? null);

        $runningBalance = $openingBalance;

        foreach ($lines as $line) {
            $lineDebit = $this->normalizeDecimal($line->getAttribute('debit'));
            $lineCredit = $this->normalizeDecimal($line->getAttribute('credit'));
            $runningBalance = $this->safeBcadd($runningBalance, $lineDebit);
            $runningBalance = $this->safeBcsub($runningBalance, $lineCredit);
        }

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'lines' => $lines,
            'closing_balance' => $runningBalance,
        ];
    }

    /**
     * Batched equivalent of getLedgerSummary() for many accounts.
     *
     * Produces identical summaries (same line selection, ordering, opening
     * balance and running closing balance) using two queries total — one
     * for all filtered ledger lines and one aggregated query for opening
     * balances — instead of two queries per account.
     *
     * @param  EloquentCollection<int, Account>  $accounts
     * @param  array{from_date?: string, to_date?: string, journal_id?: int|null, search?: string}  $filters
     * @return array<int, array{account: Account, opening_balance: numeric-string, lines: EloquentCollection<int, JournalEntryLine>, closing_balance: numeric-string}>
     */
    public function getLedgerSummaries(EloquentCollection $accounts, Company $company, FiscalYear $fiscalYear, array $filters = []): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $accountIds = array_values(array_map(
            fn (int|string $key): int => (int) $key,
            $accounts->modelKeys(),
        ));

        $linesByAccount = $this->getAccountsLedgerLines($accountIds, $company, $fiscalYear, $filters)
            ->groupBy('account_id');

        $openingsByAccount = $this->getOpeningBalances($accountIds, $company, $fiscalYear, $filters['from_date'] ?? null);

        $summaries = [];

        foreach ($accounts as $account) {
            /** @var EloquentCollection<int, JournalEntryLine> $lines */
            $lines = $linesByAccount->get($account->id, new EloquentCollection);

            $runningBalance = $openingsByAccount[$account->id] ?? '0.000';

            foreach ($lines as $line) {
                $lineDebit = $this->normalizeDecimal($line->getAttribute('debit'));
                $lineCredit = $this->normalizeDecimal($line->getAttribute('credit'));
                $runningBalance = $this->safeBcadd($runningBalance, $lineDebit);
                $runningBalance = $this->safeBcsub($runningBalance, $lineCredit);
            }

            $summaries[$account->id] = [
                'account' => $account,
                'opening_balance' => $openingsByAccount[$account->id] ?? '0.000',
                'lines' => $lines,
                'closing_balance' => $runningBalance,
            ];
        }

        return $summaries;
    }

    /**
     * All filtered ledger lines for several accounts in one query, ordered
     * exactly like getAccountLedger().
     *
     * @param  list<int>  $accountIds
     * @param  array{from_date?: string, to_date?: string, journal_id?: int|null, search?: string}  $filters
     * @return EloquentCollection<int, JournalEntryLine>
     */
    private function getAccountsLedgerLines(array $accountIds, Company $company, FiscalYear $fiscalYear, array $filters = []): EloquentCollection
    {
        return JournalEntryLine::query()
            ->select([
                'journal_entry_lines.id',
                'journal_entry_lines.account_id',
                'journal_entry_lines.description as line_description',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entries.entry_date',
                'journal_entries.entry_number',
                'journal_entries.reference',
                'journal_entries.description as entry_description',
                'journals.code as journal_code',
                'journals.name as journal_name',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED)
            ->when(isset($filters['from_date']) && $filters['from_date'] !== '', function ($q) use ($filters) {
                $fromDate = $filters['from_date'] ?? '';
                $q->where('journal_entries.entry_date', '>=', $fromDate);
            })
            ->when(isset($filters['to_date']) && $filters['to_date'] !== '', function ($q) use ($filters) {
                $toDate = $filters['to_date'] ?? '';
                $q->where('journal_entries.entry_date', '<=', $toDate);
            })
            ->when(isset($filters['journal_id']), function ($q) use ($filters) {
                $q->where('journal_entries.journal_id', $filters['journal_id']);
            })
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'] ?? '';
                $q->where(function ($q2) use ($search) {
                    $q2->where('journal_entries.reference', 'like', "%{$search}%")
                        ->orWhere('journal_entries.description', 'like', "%{$search}%")
                        ->orWhere('journal_entry_lines.description', 'like', "%{$search}%");
                });
            })
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journals.code')
            ->orderBy('journal_entries.entry_number')
            ->orderBy('journal_entry_lines.id')
            ->get();
    }

    /**
     * Opening balances (debit-credit before from_date) for several accounts
     * in one grouped query.
     *
     * @param  list<int>  $accountIds
     * @return array<int, numeric-string>
     */
    private function getOpeningBalances(array $accountIds, Company $company, FiscalYear $fiscalYear, ?string $fromDate): array
    {
        if ($fromDate === null || $fromDate === '') {
            return [];
        }

        $rows = DB::table('journal_entry_lines')
            ->select([
                'journal_entry_lines.account_id',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED)
            ->where('journal_entries.entry_date', '<', $fromDate)
            ->groupBy('journal_entry_lines.account_id')
            ->get();

        $openings = [];

        foreach ($rows as $row) {
            $debit = number_format((float) $row->total_debit, 3, '.', '');
            $credit = number_format((float) $row->total_credit, 3, '.', '');

            $openings[(int) $row->account_id] = bcsub($debit, $credit, 3);
        }

        return $openings;
    }

    /**
     * Get available journals for the current context.
     *
     * @return Collection<int, Journal>
     */
    public function getJournalsForContext(Company $company, FiscalYear $fiscalYear): EloquentCollection
    {
        return Journal::where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderBy('code')
            ->get();
    }
}
