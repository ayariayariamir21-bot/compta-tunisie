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
 * Read-only Balance Sheet (Bilan) report service.
 *
 * Derives the financial position of a company at a given date from POSTED
 * journal entries only. Account balances are aggregated with the tested
 * TrialBalanceService logic and normalized by account nature:
 *
 * - asset / expense accounts: natural debit balance  = total_debit - total_credit
 * - liability / equity / revenue accounts: natural credit balance = total_credit - total_debit
 *
 * The fiscal-year result is calculated as revenues - expenses and presented
 * inside Capitaux propres. No closing entry is ever created.
 *
 * @phpstan-type RawRow array{account_id: int, code: string, name: string, depth: int, is_group: bool, balance: numeric-string, subtotal: numeric-string}
 * @phpstan-type DisplayRow array{account_id: int, code: string, name: string, depth: int, is_group: bool, balance: string, subtotal: string}
 */
class BalanceSheetService
{
    /**
     * A rendered balance-sheet row.
     */
    public function __construct(private TrialBalanceService $trialBalanceService) {}

    /**
     * Validate that the context is coherent for a balance sheet at asOfDate.
     *
     * Historical dates inside already-closed periods are allowed on purpose:
     * this is a reporting snapshot, not a transaction screen. The date must
     * simply be well-formed and belong to the selected fiscal year.
     *
     * @throws InvalidArgumentException
     */
    public function validateContext(Company $company, FiscalYear $fiscalYear, string $asOfDate): void
    {
        if ($fiscalYear->company_id !== $company->id) {
            throw new InvalidArgumentException('L\'exercice n\'appartient pas à cette société.');
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $asOfDate)?->startOfDay();
        } catch (InvalidArgumentException) {
            $date = false;
        }

        if ($date === false || $date->format('Y-m-d') !== $asOfDate) {
            throw new InvalidArgumentException('La date du bilan est invalide.');
        }

        $start = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $end = Carbon::parse($fiscalYear->end_date)->startOfDay();

        if ($date->lt($start) || $date->gt($end)) {
            $formattedStart = $start->format('d/m/Y');
            $formattedEnd = $end->format('d/m/Y');

            throw new InvalidArgumentException("La date du bilan doit être comprise entre le {$formattedStart} et le {$formattedEnd}.");
        }
    }

    /**
     * Build the full balance sheet for the current company/fiscal year at asOfDate.
     *
     * @return array{as_of_date: string, assets: Collection<int, DisplayRow>, liabilities: Collection<int, DisplayRow>, equity: Collection<int, DisplayRow>, result: array{revenue_total: numeric-string, expense_total: numeric-string, value: numeric-string}, totals: array{total_assets: numeric-string, total_liabilities: numeric-string, total_equity_accounts: numeric-string, total_equity_with_result: numeric-string, total_passif_capitaux: numeric-string, difference: numeric-string}, is_balanced: bool}
     */
    public function getBalanceSheet(Company $company, FiscalYear $fiscalYear, string $asOfDate, bool $includeZeroBalance = false): array
    {
        $this->validateContext($company, $fiscalYear, $asOfDate);

        [$balancesByType, $accountsById] = $this->fetchContextData($company, $fiscalYear, $asOfDate);

        [$assets, $totalAssets] = $this->buildSection(AccountType::Asset, $accountsById, $balancesByType[AccountType::Asset->value], $includeZeroBalance);
        [$liabilities, $totalLiabilities] = $this->buildSection(AccountType::Liability, $accountsById, $balancesByType[AccountType::Liability->value], $includeZeroBalance);
        [$equity, $totalEquityAccounts] = $this->buildSection(AccountType::Equity, $accountsById, $balancesByType[AccountType::Equity->value], $includeZeroBalance);

        $revenueTotal = $this->sumBalances($balancesByType[AccountType::Revenue->value]);
        $expenseTotal = $this->sumBalances($balancesByType[AccountType::Expense->value]);
        $result = $this->calculateResult($revenueTotal, $expenseTotal);

        $totals = $this->calculateTotals($totalAssets, $totalLiabilities, $totalEquityAccounts, $result);

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'result' => [
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'value' => $result,
            ],
            'totals' => $totals,
            'is_balanced' => bccomp($totals['difference'], '0.000', 3) === 0,
        ];
    }

    /**
     * Asset section rows ordered for display (hierarchical, code-ordered).
     *
     * @return Collection<int, DisplayRow>
     */
    public function getAssetAccounts(Company $company, FiscalYear $fiscalYear, string $asOfDate, bool $includeZeroBalance = false): Collection
    {
        return $this->getSectionRows(AccountType::Asset, $company, $fiscalYear, $asOfDate, $includeZeroBalance);
    }

    /**
     * Liability (Passif) section rows ordered for display.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getLiabilityAccounts(Company $company, FiscalYear $fiscalYear, string $asOfDate, bool $includeZeroBalance = false): Collection
    {
        return $this->getSectionRows(AccountType::Liability, $company, $fiscalYear, $asOfDate, $includeZeroBalance);
    }

    /**
     * Equity (Capitaux propres) section rows ordered for display, excluding the computed result.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getEquityAccounts(Company $company, FiscalYear $fiscalYear, string $asOfDate, bool $includeZeroBalance = false): Collection
    {
        return $this->getSectionRows(AccountType::Equity, $company, $fiscalYear, $asOfDate, $includeZeroBalance);
    }

    /**
     * Revenue accounts with credit-normalized balances used for the result calculation.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getRevenueAccounts(Company $company, FiscalYear $fiscalYear, string $asOfDate): Collection
    {
        return $this->getSectionRows(AccountType::Revenue, $company, $fiscalYear, $asOfDate, true);
    }

    /**
     * Expense accounts with debit-normalized balances used for the result calculation.
     *
     * @return Collection<int, DisplayRow>
     */
    public function getExpenseAccounts(Company $company, FiscalYear $fiscalYear, string $asOfDate): Collection
    {
        return $this->getSectionRows(AccountType::Expense, $company, $fiscalYear, $asOfDate, true);
    }

    /**
     * Result of the fiscal year up to asOfDate: revenues - expenses.
     *
     * Positive value = profit (bénéfice), negative value = loss (perte).
     *
     * @param  numeric-string  $revenueTotal
     * @param  numeric-string  $expenseTotal
     * @return numeric-string
     */
    public function calculateResult(string $revenueTotal, string $expenseTotal): string
    {
        return bcsub($revenueTotal, $expenseTotal, 3);
    }

    /**
     * Compute section totals and the balance check from section subtotals.
     *
     * @param  numeric-string  $totalAssets
     * @param  numeric-string  $totalLiabilities
     * @param  numeric-string  $totalEquityAccounts
     * @param  numeric-string  $result
     * @return array{total_assets: numeric-string, total_liabilities: numeric-string, total_equity_accounts: numeric-string, total_equity_with_result: numeric-string, total_passif_capitaux: numeric-string, difference: numeric-string}
     */
    public function calculateTotals(string $totalAssets, string $totalLiabilities, string $totalEquityAccounts, string $result): array
    {
        $totalEquityWithResult = bcadd($totalEquityAccounts, $result, 3);
        $totalPassifCapitaux = bcadd($totalLiabilities, $totalEquityWithResult, 3);

        return [
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity_accounts' => $totalEquityAccounts,
            'total_equity_with_result' => $totalEquityWithResult,
            'total_passif_capitaux' => $totalPassifCapitaux,
            'difference' => bcsub($totalAssets, $totalPassifCapitaux, 3),
        ];
    }

    /**
     * Flat summary for headline cards.
     *
     * @param  array{as_of_date: string, assets: Collection<int, DisplayRow>, liabilities: Collection<int, DisplayRow>, equity: Collection<int, DisplayRow>, result: array{revenue_total: numeric-string, expense_total: numeric-string, value: numeric-string}, totals: array{total_assets: numeric-string, total_liabilities: numeric-string, total_equity_accounts: numeric-string, total_equity_with_result: numeric-string, total_passif_capitaux: numeric-string, difference: numeric-string}, is_balanced: bool}  $report
     * @return array{as_of_date: string, total_assets: numeric-string, total_liabilities: numeric-string, total_equity: numeric-string, result: numeric-string, total_passif_capitaux: numeric-string, difference: numeric-string, is_balanced: bool, has_profit: bool}
     */
    public function getSummary(array $report): array
    {
        return [
            'as_of_date' => $report['as_of_date'],
            'total_assets' => $report['totals']['total_assets'],
            'total_liabilities' => $report['totals']['total_liabilities'],
            'total_equity' => $report['totals']['total_equity_with_result'],
            'result' => $report['result']['value'],
            'total_passif_capitaux' => $report['totals']['total_passif_capitaux'],
            'difference' => $report['totals']['difference'],
            'is_balanced' => $report['is_balanced'],
            'has_profit' => bccomp($report['result']['value'], '0.000', 3) >= 0,
        ];
    }

    /**
     * Rows for one section, built from freshly fetched context data.
     *
     * @return Collection<int, DisplayRow>
     */
    private function getSectionRows(AccountType $type, Company $company, FiscalYear $fiscalYear, string $asOfDate, bool $includeZeroBalance): Collection
    {
        $this->validateContext($company, $fiscalYear, $asOfDate);

        [$balancesByType, $accountsById] = $this->fetchContextData($company, $fiscalYear, $asOfDate);

        [$rows] = $this->buildSection($type, $accountsById, $balancesByType[$type->value], $includeZeroBalance);

        return $rows;
    }

    /**
     * Fetch normalized balances per account type and the account index in one pass.
     *
     * Balances come from TrialBalanceService (posted entries only, scoped to
     * company + fiscal year + date window). Normalization applies the account's
     * accounting nature so opposite ("contra") balances stay negative instead
     * of being discarded.
     *
     * @return array{array<string, array<int, numeric-string>>, Collection<int|string, Account>}
     */
    private function fetchContextData(Company $company, FiscalYear $fiscalYear, string $asOfDate): array
    {
        /** @var array<string, array<int, numeric-string>> $balancesByType */
        $balancesByType = [
            AccountType::Asset->value => [],
            AccountType::Liability->value => [],
            AccountType::Equity->value => [],
            AccountType::Revenue->value => [],
            AccountType::Expense->value => [],
        ];

        $rows = $this->trialBalanceService->getAccountTotals($company, $fiscalYear, [
            'from_date' => Carbon::parse($fiscalYear->start_date)->format('Y-m-d'),
            // End-of-day bound so entries dated exactly on asOfDate stay inside
            // the window even where entry timestamps carry a time component.
            'to_date' => Carbon::parse($asOfDate)->endOfDay()->format('Y-m-d H:i:s'),
            'include_zero_balance' => '1',
        ]);

        foreach ($rows as $row) {
            $net = bcsub(
                number_format((float) $row->total_debit, 3, '.', ''),
                number_format((float) $row->total_credit, 3, '.', ''),
                3,
            );

            $type = AccountType::from((string) $row->account_type);
            $isNaturalDebit = in_array($type, [AccountType::Asset, AccountType::Expense], true);
            $balance = $isNaturalDebit ? $net : bcsub('0.000', $net, 3);

            $balancesByType[$type->value][(int) $row->id] = $balance;
        }

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderBy('code')
            ->get();

        $accountsById = $accounts->keyBy('id');

        return [$balancesByType, $accountsById];
    }

    /**
     * Build one hierarchical section from same-typed accounts.
     *
     * The chart allows mixed account types inside a subtree (e.g. class 4
     * "Tiers" holds both asset and liability children), so each node is
     * re-parented to its nearest ancestor of the SAME type. This uses only
     * existing parent_id data — no new hierarchy fields are introduced.
     *
     * The section total is computed from the raw rows BEFORE they are wrapped
     * into a Collection, keeping full numeric precision for accounting math.
     *
     * @param  array<int, numeric-string>  $balances
     * @param  Collection<int|string, Account>  $accountsById
     * @return array{0: Collection<int, DisplayRow>, 1: numeric-string}
     */
    private function buildSection(AccountType $type, Collection $accountsById, array $balances, bool $includeZeroBalance): array
    {
        /** @var array<int, array<int, int>> $childrenMap Parent id (or 0 for roots) => child account ids */
        $childrenMap = [];

        foreach ($accountsById as $account) {
            // Accounts without movements in the window are absent from the
            // aggregated balances; they participate as zero-balance nodes so
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
            $this->computeSubtotal($rootId, $childrenMap, $balances, $subtotalCache);
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

            $subtotal = $this->balanceOrZero($subtotalCache, $accountId);
            $balance = $this->balanceOrZero($balances, $accountId);

            if (! $includeZeroBalance && bccomp($subtotal, '0.000', 3) === 0 && bccomp($balance, '0.000', 3) === 0) {
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
                'balance' => $balance,
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
     * Balance lookup defaulting to a zero amount.
     *
     * @param  array<int, numeric-string>  $balances
     * @return numeric-string
     */
    private function balanceOrZero(array $balances, int $accountId): string
    {
        return $balances[$accountId] ?? '0.000';
    }

    /**
     * Recursively compute the subtree subtotal (own balance + all descendants).
     *
     * @param  array<int, array<int, int>>  $childrenMap
     * @param  array<int, numeric-string>  $balances
     * @param  array<int, numeric-string>  $subtotalCache
     * @return numeric-string
     */
    private function computeSubtotal(int $accountId, array $childrenMap, array $balances, array &$subtotalCache): string
    {
        if (isset($subtotalCache[$accountId])) {
            return $subtotalCache[$accountId];
        }

        $own = $balances[$accountId] ?? '0.000';
        $total = $own;

        foreach ($childrenMap[$accountId] ?? [] as $childId) {
            $total = bcadd($total, $this->computeSubtotal($childId, $childrenMap, $balances, $subtotalCache), 3);
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

    /**
     * Sum every normalized balance of a type.
     *
     * @param  array<int, numeric-string>  $balances
     * @return numeric-string
     */
    private function sumBalances(array $balances): string
    {
        $total = '0.000';

        foreach ($balances as $balance) {
            $total = bcadd($total, $balance, 3);
        }

        return $total;
    }
}
