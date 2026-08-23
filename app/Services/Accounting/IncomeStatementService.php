<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Read-only Income Statement (Compte de résultat) report service.
 *
 * Derives the period performance of a company from POSTED journal entries
 * only. Movements are aggregated with the tested TrialBalanceService logic
 * and normalized by account nature so the report displays positive business
 * amounts:
 *
 * - revenue accounts: amount = total_credit - total_debit (credit = income)
 * - expense accounts: amount = total_debit - total_credit (debit = cost)
 *
 * Contra ("abnormal") balances are never discarded: they surface as negative
 * amounts, exactly like the Balance Sheet sign-aware rules.
 *
 * The existing Account model only classifies accounts through the
 * AccountType enum (revenue / expense). There is no operating vs financial
 * vs exceptional metadata in the chart, therefore the report exposes a
 * single result: Résultat net = Total produits - Total charges. The chart's
 * hierarchy (parent_id tree) is used for display rows and subtotals.
 *
 * @phpstan-type RawRow array{account_id: int, code: string, name: string, depth: int, is_group: bool, amount: numeric-string, subtotal: numeric-string}
 * @phpstan-type DisplayRow array{account_id: int, code: string, name: string, depth: int, is_group: bool, amount: string, subtotal: string}
 */
class IncomeStatementService
{
    public function __construct(private TrialBalanceService $trialBalanceService) {}

    /**
     * Validate that the context is coherent for an income statement period.
     *
     * Historical dates inside already-closed periods are allowed on purpose:
     * this is a reporting screen, not a transaction screen. Both dates must
     * be well-formed, belong to the selected fiscal year and be ordered.
     *
     * @throws InvalidArgumentException
     */
    public function validateContext(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): void
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromDate)?->startOfDay();
        } catch (InvalidArgumentException) {
            $from = false;
        }

        if ($from === false || $from->format('Y-m-d') !== $fromDate) {
            throw new InvalidArgumentException('La date de début est invalide.');
        }

        try {
            $to = Carbon::createFromFormat('Y-m-d', $toDate)?->startOfDay();
        } catch (InvalidArgumentException) {
            $to = false;
        }

        if ($to === false || $to->format('Y-m-d') !== $toDate) {
            throw new InvalidArgumentException('La date de fin est invalide.');
        }

        $start = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $end = Carbon::parse($fiscalYear->end_date)->startOfDay();

        if ($from->lt($start) || $from->gt($end) || $to->lt($start) || $to->gt($end)) {
            $formattedStart = $start->format('d/m/Y');
            $formattedEnd = $end->format('d/m/Y');

            throw new InvalidArgumentException("Les dates du compte de résultat doivent être comprises entre le {$formattedStart} et le {$formattedEnd}.");
        }

        if ($from->gt($to)) {
            throw new InvalidArgumentException('La date de début doit être antérieure ou égale à la date de fin.');
        }
    }

    /**
     * Build the full income statement for the current company/fiscal year period.
     *
     * Defaults chosen by the Livewire page: from_date = fiscal-year start,
     * to_date = today clamped inside the fiscal year.
     *
     * @return array{from_date: string, to_date: string, revenues: Collection<int, DisplayRow>, expenses: Collection<int, DisplayRow>, totals: array{total_revenue: numeric-string, total_expenses: numeric-string, net_result: numeric-string}, is_profit: bool, is_loss: bool}
     */
    public function getIncomeStatement(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate, bool $includeZeroBalance = false): array
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [$amountsByType, $accountsById] = $this->fetchMovements($company, $fiscalYear, $fromDate, $toDate);

        [$revenues, $totalRevenue] = $this->buildSection(AccountType::Revenue, $accountsById, $amountsByType[AccountType::Revenue->value], $includeZeroBalance);
        [$expenses, $totalExpenses] = $this->buildSection(AccountType::Expense, $accountsById, $amountsByType[AccountType::Expense->value], $includeZeroBalance);

        $totals = $this->calculateTotals($totalRevenue, $totalExpenses);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'totals' => $totals,
            'is_profit' => bccomp($totals['net_result'], '0.000', 3) > 0,
            'is_loss' => bccomp($totals['net_result'], '0.000', 3) < 0,
        ];
    }

    /**
     * Revenue (Produits) section rows ordered for display over the period.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getRevenueAccounts(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate, bool $includeZeroBalance = false): Collection
    {
        return $this->getSectionRows(AccountType::Revenue, $company, $fiscalYear, $fromDate, $toDate, $includeZeroBalance);
    }

    /**
     * Expense (Charges) section rows ordered for display over the period.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getExpenseAccounts(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate, bool $includeZeroBalance = false): Collection
    {
        return $this->getSectionRows(AccountType::Expense, $company, $fiscalYear, $fromDate, $toDate, $includeZeroBalance);
    }

    /**
     * Operating result of the period: produits - charges.
     *
     * The chart provides no financial/exceptional classification, so the
     * operating result equals the overall period result.
     *
     * @param  numeric-string  $totalRevenue
     * @param  numeric-string  $totalExpenses
     * @return numeric-string
     */
    public function getOperatingResult(string $totalRevenue, string $totalExpenses): string
    {
        return bcsub($totalRevenue, $totalExpenses, 3);
    }

    /**
     * Net result of the period: produits - charges.
     *
     * Identical to getOperatingResult() by design — no separate financial
     * or exceptional account categories exist in the current chart model.
     *
     * @param  numeric-string  $totalRevenue
     * @param  numeric-string  $totalExpenses
     * @return numeric-string
     */
    public function getNetResult(string $totalRevenue, string $totalExpenses): string
    {
        return $this->getOperatingResult($totalRevenue, $totalExpenses);
    }

    /**
     * Compute the report totals and net result from section totals.
     *
     * @param  numeric-string  $totalRevenue
     * @param  numeric-string  $totalExpenses
     * @return array{total_revenue: numeric-string, total_expenses: numeric-string, net_result: numeric-string}
     */
    public function calculateTotals(string $totalRevenue, string $totalExpenses): array
    {
        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_result' => bcsub($totalRevenue, $totalExpenses, 3),
        ];
    }

    /**
     * Flat summary for headline cards.
     *
     * @param  array{from_date: string, to_date: string, revenues: Collection<int, DisplayRow>, expenses: Collection<int, DisplayRow>, totals: array{total_revenue: numeric-string, total_expenses: numeric-string, net_result: numeric-string}, is_profit: bool, is_loss: bool}  $report
     * @return array{from_date: string, to_date: string, total_revenue: numeric-string, total_expenses: numeric-string, net_result: numeric-string, operating_result: numeric-string, has_profit: bool, has_loss: bool, is_break_even: bool}
     */
    public function getSummary(array $report): array
    {
        $netResult = $report['totals']['net_result'];

        return [
            'from_date' => $report['from_date'],
            'to_date' => $report['to_date'],
            'total_revenue' => $report['totals']['total_revenue'],
            'total_expenses' => $report['totals']['total_expenses'],
            'net_result' => $netResult,
            // No financial/exceptional split exists in the chart: the
            // operating result is the whole period result.
            'operating_result' => $netResult,
            'has_profit' => bccomp($netResult, '0.000', 3) > 0,
            'has_loss' => bccomp($netResult, '0.000', 3) < 0,
            'is_break_even' => bccomp($netResult, '0.000', 3) === 0,
        ];
    }

    /**
     * Rows for one section, built from freshly fetched period movements.
     *
     * @return Collection<int, DisplayRow>
     */
    private function getSectionRows(AccountType $type, Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate, bool $includeZeroBalance): Collection
    {
        $this->validateContext($company, $fiscalYear, $fromDate, $toDate);

        [$amountsByType, $accountsById] = $this->fetchMovements($company, $fiscalYear, $fromDate, $toDate);

        [$rows] = $this->buildSection($type, $accountsById, $amountsByType[$type->value], $includeZeroBalance);

        return $rows;
    }

    /**
     * Fetch normalized per-account movements for the period in one pass.
     *
     * Amounts come from TrialBalanceService (posted entries only, scoped to
     * company + fiscal year + inclusive date window). The lower bound uses a
     * bare date and the upper bound an explicit end-of-day timestamp so both
     * boundaries stay inclusive even where entry dates carry a time component
     * (SQLite compares entry_date values as strings).
     *
     * Normalization applies the account nature:
     * revenue amount = credits - debits, expense amount = debits - credits.
     *
     * @return array{array<string, array<int, numeric-string>>, Collection<int|string, Account>}
     */
    private function fetchMovements(Company $company, FiscalYear $fiscalYear, string $fromDate, string $toDate): array
    {
        /** @var array<string, array<int, numeric-string>> $amountsByType */
        $amountsByType = [
            AccountType::Revenue->value => [],
            AccountType::Expense->value => [],
        ];

        $rows = $this->trialBalanceService->getAccountTotals($company, $fiscalYear, [
            'from_date' => Carbon::parse($fromDate)->format('Y-m-d'),
            'to_date' => Carbon::parse($toDate)->endOfDay()->format('Y-m-d H:i:s'),
            'include_zero_balance' => '1',
        ]);

        foreach ($rows as $row) {
            $debit = number_format((float) $row->total_debit, 3, '.', '');
            $credit = number_format((float) $row->total_credit, 3, '.', '');

            $type = AccountType::from((string) $row->account_type);

            if (! in_array($type, [AccountType::Revenue, AccountType::Expense], true)) {
                continue;
            }

            $isRevenue = $type === AccountType::Revenue;
            $amountsByType[$type->value][(int) $row->id] = $isRevenue
                ? bcsub($credit, $debit, 3)
                : bcsub($debit, $credit, 3);
        }

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderBy('code')
            ->get();

        $accountsById = $accounts->keyBy('id');

        return [$amountsByType, $accountsById];
    }

    /**
     * Build one hierarchical section from same-typed accounts.
     *
     * The chart allows mixed account types inside a subtree, so each node is
     * re-parented to its nearest ancestor of the SAME type (same rule as the
     * balance sheet). The section total is computed from the raw rows BEFORE
     * they are wrapped into a Collection, keeping full numeric precision for
     * accounting math.
     *
     * @param  array<int, numeric-string>  $amounts
     * @param  Collection<int|string, Account>  $accountsById
     * @return array{0: Collection<int, DisplayRow>, 1: numeric-string}
     */
    private function buildSection(AccountType $type, Collection $accountsById, array $amounts, bool $includeZeroBalance): array
    {
        /** @var array<int, array<int, int>> $childrenMap Parent id (or 0 for roots) => child account ids */
        $childrenMap = [];

        foreach ($accountsById as $account) {
            // Accounts without movements in the window are absent from the
            // aggregated amounts; they participate as zero-amount nodes so
            // group headers can still roll up their descendants' subtotals.
            if ($this->toAccountType($account->account_type) !== $type) {
                continue;
            }

            $nearestAncestorId = $this->nearestSameTypeAncestorId($account, $accountsById, $type);
            $childrenMap[$nearestAncestorId][] = $account->id;
        }

        /** @var array<int, numeric-string> $subtotalCache */
        $subtotalCache = [];
        $roots = $childrenMap[0] ?? [];

        foreach ($roots as $rootId) {
            $this->computeSubtotal($rootId, $childrenMap, $amounts, $subtotalCache);
        }

        // Depth-first pre-order traversal (iterative) producing display rows.
        /** @var list<RawRow> $rows */
        $rows = [];

        /** @var list<array{int, int}> $stack Pending [account id, depth] pairs */
        $stack = [];

        foreach (array_reverse($roots) as $rootId) {
            $stack[] = [$rootId, 0];
        }

        while ($stack !== []) {
            /** @var array{int, int} $pending */
            $pending = array_pop($stack);
            [$accountId, $depth] = $pending;

            $subtotal = $this->amountOrZero($subtotalCache, $accountId);
            $amount = $this->amountOrZero($amounts, $accountId);

            if (! $includeZeroBalance && bccomp($subtotal, '0.000', 3) === 0 && bccomp($amount, '0.000', 3) === 0) {
                continue;
            }

            /** @var Account $account */
            $account = $accountsById->get($accountId);
            $childIds = $childrenMap[$accountId] ?? [];

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'depth' => $depth,
                'is_group' => $childIds !== [],
                'amount' => $amount,
                'subtotal' => $subtotal,
            ];

            foreach (array_reverse($childIds) as $childId) {
                $stack[] = [$childId, $depth + 1];
            }
        }

        return [collect($rows), $this->sumSubtotals($rows)];
    }

    /**
     * Find the nearest ancestor of the given type; returns 0 when none exists (root).
     *
     * @param  Collection<int|string, Account>  $accountsById
     */
    private function nearestSameTypeAncestorId(Account $account, Collection $accountsById, AccountType $type): int
    {
        $currentId = $account->parent_id;

        while ($currentId !== null && $accountsById->has($currentId)) {
            /** @var Account $ancestor */
            $ancestor = $accountsById->get($currentId);

            if ($this->toAccountType($ancestor->account_type) === $type) {
                return $ancestor->id;
            }

            $currentId = $ancestor->parent_id;
        }

        return 0;
    }

    /**
     * Normalize a raw account type value (enum instance or backed string) to the enum.
     */
    private function toAccountType(mixed $value): AccountType
    {
        if ($value instanceof AccountType) {
            return $value;
        }

        return AccountType::from((string) $value);
    }

    /**
     * Amount lookup defaulting to a zero amount.
     *
     * @param  array<int, numeric-string>  $amounts
     * @return numeric-string
     */
    private function amountOrZero(array $amounts, int $accountId): string
    {
        return $amounts[$accountId] ?? '0.000';
    }

    /**
     * Recursively compute the subtree subtotal (own movement + all descendants).
     *
     * @param  array<int, array<int, int>>  $childrenMap
     * @param  array<int, numeric-string>  $amounts
     * @param  array<int, numeric-string>  $subtotalCache
     * @return numeric-string
     */
    private function computeSubtotal(int $accountId, array $childrenMap, array $amounts, array &$subtotalCache): string
    {
        if (isset($subtotalCache[$accountId])) {
            return $subtotalCache[$accountId];
        }

        $own = $amounts[$accountId] ?? '0.000';
        $total = $own;

        foreach ($childrenMap[$accountId] ?? [] as $childId) {
            $total = bcadd($total, $this->computeSubtotal($childId, $childrenMap, $amounts, $subtotalCache), 3);
        }

        return $subtotalCache[$accountId] = $total;
    }

    /**
     * Sum root subtotals of a raw section (each account counted exactly once).
     *
     * @param  list<RawRow>  $rows
     * @return numeric-string
     */
    private function sumSubtotals(array $rows): string
    {
        $total = '0.000';

        foreach ($rows as $row) {
            if ($row['depth'] === 0) {
                $total = bcadd($total, $row['subtotal'], 3);
            }
        }

        return $total;
    }
}
