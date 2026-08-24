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
        $companyId = session('current_company_id');
        $companyId = is_numeric($companyId) ? (int) $companyId : null;

        $fiscalYearId = session('current_fiscal_year_id');
        $fiscalYearId = is_numeric($fiscalYearId) ? (int) $fiscalYearId : null;

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
        $companyId = session('current_company_id');
        $fiscalYearId = session('current_fiscal_year_id');

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
        $companyId = session('current_company_id');
        $fiscalYearId = session('current_fiscal_year_id');

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

        return AccountingPeriod::where('fiscal_year_id', $fiscalYearId)
            ->where('is_open', true)
            ->orderBy('start_date', 'desc')
            ->first();
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
