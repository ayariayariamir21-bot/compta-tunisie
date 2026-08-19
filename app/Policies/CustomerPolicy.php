<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->exists();
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $user->companies()
            ->where('companies.id', $customer->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
