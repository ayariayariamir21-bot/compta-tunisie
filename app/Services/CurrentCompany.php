<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CurrentCompany
{
    private const SESSION_KEY = 'current_company_id';

    public function get(User $user): ?Company
    {
        $companyId = session(self::SESSION_KEY);

        if ($companyId) {
            $company = $user->companies()
                ->where('companies.id', $companyId)
                ->wherePivot('is_active', true)
                ->first();

            if ($company instanceof Company) {
                return $company;
            }
        }

        $company = $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('name')
            ->first();

        return $company instanceof Company ? $company : null;
    }

    public function set(User $user, int $companyId): bool
    {
        $exists = $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        if (! $exists) {
            return false;
        }

        session([
            self::SESSION_KEY => $companyId,
        ]);

        return true;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
