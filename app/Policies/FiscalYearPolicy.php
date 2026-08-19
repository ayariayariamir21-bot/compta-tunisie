<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;

class FiscalYearPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->exists();
    }

    public function create(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
