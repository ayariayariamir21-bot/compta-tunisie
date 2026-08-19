<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\User;

class CurrentFiscalYear
{
    private const SESSION_KEY = 'current_fiscal_year_id';

    public function get(User $user): ?FiscalYear
    {
        $companyId = session('current_company_id');

        if (! $companyId) {
            return $this->fallback($user);
        }

        $fiscalYearId = session(self::SESSION_KEY);

        if ($fiscalYearId) {
            $fiscalYear = FiscalYear::where('id', $fiscalYearId)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if ($fiscalYear) {
                $hasAccess = $user->companies()
                    ->where('companies.id', $companyId)
                    ->wherePivot('is_active', true)
                    ->exists();

                if ($hasAccess) {
                    return $fiscalYear;
                }
            }
        }

        return $this->fallback($user);
    }

    public function set(User $user, int $fiscalYearId): bool
    {
        $companyId = session('current_company_id');

        if (! $companyId) {
            return false;
        }

        $hasCompanyAccess = $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $hasCompanyAccess) {
            return false;
        }

        $fiscalYear = FiscalYear::where('id', $fiscalYearId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $fiscalYear) {
            return false;
        }

        session([self::SESSION_KEY => $fiscalYearId]);

        session()->forget('current_accounting_period_id');

        return true;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->forget('current_accounting_period_id');
    }

    public function fallback(User $user): ?FiscalYear
    {
        $companyId = session('current_company_id');

        if (! $companyId) {
            return null;
        }

        return FiscalYear::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->first();
    }
}
