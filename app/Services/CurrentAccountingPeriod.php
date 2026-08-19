<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\User;

class CurrentAccountingPeriod
{
    private const SESSION_KEY = 'current_accounting_period_id';

    public function get(User $user): ?AccountingPeriod
    {
        $companyId = session('current_company_id');
        $fiscalYearId = session('current_fiscal_year_id');

        if (! $companyId || ! $fiscalYearId) {
            return $this->fallback($user);
        }

        $periodId = session(self::SESSION_KEY);

        if ($periodId) {
            $period = AccountingPeriod::where('id', $periodId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->first();

            if ($period) {
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
}
