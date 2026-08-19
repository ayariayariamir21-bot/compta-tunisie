<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->exists();
    }

    public function create(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function deactivate(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function setDefault(User $user, TaxRate $taxRate): bool
    {
        return $user->companies()
            ->where('companies.id', $taxRate->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
