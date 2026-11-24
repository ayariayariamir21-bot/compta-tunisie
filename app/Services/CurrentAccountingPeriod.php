<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\User;

class CurrentAccountingPeriod
{
    private const SESSION_KEY = 'current_accounting_period_id';

    private ?int $memoizedForUser = null;

    private ?int $memoizedSessionCompanyId = null;

    private ?int $memoizedSessionFiscalYearId = null;

    private ?int $memoizedSessionPeriodId = null;

    private bool $memoHasValue = false;

    private ?AccountingPeriod $memo = null;

    public function get(User $user): ?AccountingPeriod
    {
        $companyId = $this->companyId($user);
        $fiscalYearId = $this->fiscalYearId($user);

        $periodId = session(self::SESSION_KEY);
        $periodId = is_numeric($periodId) ? (int) $periodId : null;

        if (
            $this->memoHasValue
            && $this->memoizedForUser === $user->id
            && $this->memoizedSessionCompanyId === $companyId
            && $this->memoizedSessionFiscalYearId === $fiscalYearId
            && $this->memoizedSessionPeriodId === $periodId
        ) {
            return $this->memo;
        }

        $resolved = $this->resolve($user, $companyId, $fiscalYearId, $periodId);

        $this->memoizedForUser = $user->id;
        $this->memoizedSessionCompanyId = $companyId;
        $this->memoizedSessionFiscalYearId = $fiscalYearId;
        $this->memoizedSessionPeriodId = $periodId;
        $this->memoHasValue = true;
        $this->memo = $resolved;

        return $this->memo;
    }

    public function set(User $user, int $periodId): bool
    {
        $companyId = $this->companyId($user);
        $fiscalYearId = $this->fiscalYearId($user);

        if (! $companyId || ! $fiscalYearId) {
            return false;
        }

        $hasCompanyAccess = $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $hasCompanyAccess) {
            return false;
        }

        $period = AccountingPeriod::where('id', $periodId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        if (! $period) {
            return false;
        }

        session([self::SESSION_KEY => $periodId]);

        return true;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function fallback(User $user): ?AccountingPeriod
    {
        $companyId = $this->companyId($user);
        $fiscalYearId = $this->fiscalYearId($user);

        if (! $companyId || ! $fiscalYearId) {
            return null;
        }

        $hasAccess = $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $hasAccess) {
            return null;
        }

        $openPeriods = AccountingPeriod::where('fiscal_year_id', $fiscalYearId)
            ->where('is_open', true);

        $today = now()->toDateString();

        return (clone $openPeriods)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first()
            ?? $openPeriods->orderBy('start_date', 'desc')->first();
    }

    /**
     * Company in use: selected in session, or the switcher's default company.
     */
    private function companyId(User $user): ?int
    {
        return app(CurrentCompany::class)->get($user)?->id;
    }

    /**
     * Fiscal year in use: selected in session, or the current company's default year.
     */
    private function fiscalYearId(User $user): ?int
    {
        return app(CurrentFiscalYear::class)->get($user)?->id;
    }

    /**
     * Original resolution logic, extracted unchanged for memoization.
     */
    private function resolve(User $user, ?int $companyId, ?int $fiscalYearId, ?int $periodId): ?AccountingPeriod
    {
        if ($companyId !== null && $fiscalYearId !== null && $periodId !== null) {
            $period = AccountingPeriod::where('id', $periodId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->first();

            if ($period instanceof AccountingPeriod) {
                $hasAccess = $user->companies()
                    ->where('companies.id', $companyId)
                    ->wherePivot('is_active', true)
                    ->exists();

                if ($hasAccess) {
                    return $period;
                }
            }
        }

        return $this->fallback($user);
    }
}
