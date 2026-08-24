<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CurrentCompany
{
    private const SESSION_KEY = 'current_company_id';

    private ?int $memoizedForUser = null;

    private ?int $memoizedSessionCompanyId = null;

    private bool $memoHasValue = false;

    private ?Company $memo = null;

    public function get(User $user): ?Company
    {
        $sessionCompanyId = session(self::SESSION_KEY);
        $sessionCompanyId = is_numeric($sessionCompanyId) ? (int) $sessionCompanyId : null;

        // Memoize per (user, session input): a changed key (company switch,
        // acting-user change, direct session mutation) re-resolves safely.
        if (
            $this->memoHasValue
            && $this->memoizedForUser === $user->id
            && $this->memoizedSessionCompanyId === $sessionCompanyId
        ) {
            return $this->memo;
        }

        $company = null;

        if ($sessionCompanyId !== null) {
            $company = $user->companies()
                ->where('companies.id', $sessionCompanyId)
                ->wherePivot('is_active', true)
                ->first();

            if (! $company instanceof Company) {
                $company = null;
            }
        }

        if ($company === null) {
            $company = $user->companies()
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->first();
        }

        $this->memoizedForUser = $user->id;
        $this->memoizedSessionCompanyId = $sessionCompanyId;
        $this->memoHasValue = true;
        $this->memo = $company instanceof Company ? $company : null;

        return $this->memo;
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
