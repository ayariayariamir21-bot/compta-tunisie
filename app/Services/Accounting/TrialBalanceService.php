<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * Get trial balance data for the current context.
     *
     * Aggregates posted journal entry lines by account using database-level aggregation.
     *
     * @param  array{from_date?: string, to_date?: string, account_type?: string, account_id?: int, include_zero_balance?: bool}  $filters
     */
    public function getTrialBalance(Company $company, FiscalYear $fiscalYear, array $filters = []): array
    {
        $accounts = $this->getAccountTotals($company, $fiscalYear, $filters);
        $grandTotals = $this->getGrandTotals($company, $fiscalYear, $filters);

        $totalDebit = $grandTotals['total_debit'] ?? '0.000';
        $totalCredit = $grandTotals['total_credit'] ?? '0.000';
        $isBalanced = bccomp($totalDebit, $totalCredit, 3) === 0;

        return [
            'accounts' => $accounts,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'total_debit_balance' => $grandTotals['total_debit_balance'] ?? '0.000',
            'total_credit_balance' => $grandTotals['total_credit_balance'] ?? '0.000',
            'is_balanced' => $isBalanced,
            'difference' => bcsub($totalDebit, $totalCredit, 3),
        ];
    }

    /**
     * Get aggregated account totals from posted journal entry lines.
     */
    public function getAccountTotals(Company $company, FiscalYear $fiscalYear, array $filters = []): Collection
    {
        $query = Account::query()
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.account_type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->leftJoin('journal_entry_lines', function ($join) use ($company, $fiscalYear) {
                $join->on('journal_entry_lines.account_id', '=', 'accounts.id')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.company_id', '=', $company->id)
                    ->where('journal_entries.fiscal_year_id', '=', $fiscalYear->id)
                    ->where('journal_entries.status', '=', JournalEntryStatus::POSTED);
            })
            ->where('accounts.company_id', $company->id)
            ->where('accounts.fiscal_year_id', $fiscalYear->id)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.account_type');

        if (isset($filters['from_date']) && $filters['from_date'] !== '') {
            $query->whereHas('journalEntryLines.journalEntry', function ($q) use ($filters) {
                $q->where('entry_date', '>=', $filters['from_date']);
            });
        }

        if (isset($filters['to_date']) && $filters['to_date'] !== '') {
            $query->whereHas('journalEntryLines.journalEntry', function ($q) use ($filters) {
                $q->where('entry_date', '<=', $filters['to_date']);
            });
        }

        if (isset($filters['account_type']) && $filters['account_type'] !== '') {
            $query->where('accounts.account_type', $filters['account_type']);
        }

        if (isset($filters['account_id']) && $filters['account_id'] !== '') {
            $query->where('accounts.id', $filters['account_id']);
        }

        $includeZeroBalance = ($filters['include_zero_balance'] ?? '0') === '1';

        if (! $includeZeroBalance) {
            $query->havingRaw('COALESCE(SUM(journal_entry_lines.debit), 0) != 0 OR COALESCE(SUM(journal_entry_lines.credit), 0) != 0');
        }

        $results = $query->orderBy('accounts.code')->get();

        return $results->map(function ($row) {
            $debit = number_format((float) $row->total_debit, 3, '.', '');
            $credit = number_format((float) $row->total_credit, 3, '.', '');
            $net = bcsub($debit, $credit, 3);

            if (bccomp($net, '0.000', 3) > 0) {
                $row->debit_balance = $net;
                $row->credit_balance = '0.000';
            } elseif (bccomp($net, '0.000', 3) < 0) {
                $row->debit_balance = '0.000';
                $row->credit_balance = ltrim($net, '-');
            } else {
                $row->debit_balance = '0.000';
                $row->credit_balance = '0.000';
            }

            return $row;
        });
    }

    /**
     * Get grand totals for the trial balance.
     */
    public function getGrandTotals(Company $company, FiscalYear $fiscalYear, array $filters = []): array
    {
        $query = DB::table('journal_entry_lines')
            ->select([
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::POSTED);

        if (isset($filters['from_date']) && $filters['from_date'] !== '') {
            $query->where('journal_entries.entry_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date']) && $filters['to_date'] !== '') {
            $query->where('journal_entries.entry_date', '<=', $filters['to_date']);
        }

        if (isset($filters['account_type']) && $filters['account_type'] !== '') {
            $query->where('accounts.account_type', $filters['account_type']);
        }

        if (isset($filters['account_id']) && $filters['account_id'] !== '') {
            $query->where('accounts.id', $filters['account_id']);
        }

        $result = $query->first();

        $totalDebit = number_format((float) ($result->total_debit ?? 0), 3, '.', '');
        $totalCredit = number_format((float) ($result->total_credit ?? 0), 3, '.', '');

        $totalNet = bcsub($totalDebit, $totalCredit, 3);

        if (bccomp($totalNet, '0.000', 3) > 0) {
            $totalDebitBalance = $totalNet;
            $totalCreditBalance = '0.000';
        } elseif (bccomp($totalNet, '0.000', 3) < 0) {
            $totalDebitBalance = '0.000';
            $totalCreditBalance = ltrim($totalNet, '-');
        } else {
            $totalDebitBalance = '0.000';
            $totalCreditBalance = '0.000';
        }

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'total_debit_balance' => $totalDebitBalance,
            'total_credit_balance' => $totalCreditBalance,
        ];
    }
}
