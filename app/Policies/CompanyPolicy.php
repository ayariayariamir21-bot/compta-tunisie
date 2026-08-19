<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
