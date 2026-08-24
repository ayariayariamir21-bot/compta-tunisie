<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\User;

class CurrentFiscalYear
{
    private const SESSION_KEY = 'current_fiscal_year_id';

    private ?int $memoizedForUser = null;

    private ?int $memoizedSessionCompanyId = null;

    private ?int $memoizedSessionFiscalYearId = null;

    private bool $memoHasValue = false;

    private ?FiscalYear $memo = null;

    public function get(User $user): ?FiscalYear
    {
        $companyId = session('current_company_id');
        $companyId = is_numeric($companyId) ? (int) $companyId : null;

        $fiscalYearId = session(self::SESSION_KEY);
        $fiscalYearId = is_numeric($fiscalYearId) ? (int) $fiscalYearId : null;

        // Memoize per (user, company input, fiscal-year input); any change
        // re-resolves, so external session mutation stays safe.
        if (
            $this->memoHasValue
            && $this->memoizedForUser === $user->id
            && $this->memoizedSessionCompanyId === $companyId
            && $this->memoizedSessionFiscalYearId === $fiscalYearId
        ) {
            return $this->memo;
        }

        $resolved = $this->resolve($user, $companyId, $fiscalYearId);

        $this->memoizedForUser = $user->id;
        $this->memoizedSessionCompanyId = $companyId;
        $this->memoizedSessionFiscalYearId = $fiscalYearId;
        $this->memoHasValue = true;
        $this->memo = $resolved;

        return $this->memo;
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

    /**
     * Original resolution logic, extracted unchanged for memoization.
     */
    private function resolve(User $user, ?int $companyId, ?int $fiscalYearId): ?FiscalYear
    {
        if ($companyId !== null && $fiscalYearId !== null) {
            $fiscalYear = FiscalYear::where('id', $fiscalYearId)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if ($fiscalYear instanceof FiscalYear) {
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
}
