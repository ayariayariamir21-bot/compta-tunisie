<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $user->companies()
            ->where('companies.id', $account->company_id)
            ->exists();
    }

    public function create(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $account->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $account->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        if ($account->hasChildren()) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $account->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $account->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
