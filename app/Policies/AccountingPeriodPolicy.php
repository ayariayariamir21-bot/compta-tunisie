<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;

class AccountingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccountingPeriod $accountingPeriod): bool
    {
        return $user->companies()
            ->where('companies.id', $accountingPeriod->fiscalYear->company_id)
            ->exists();
    }

    public function update(User $user, AccountingPeriod $accountingPeriod): bool
    {
        if ($accountingPeriod->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $accountingPeriod->fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function close(User $user, AccountingPeriod $accountingPeriod): bool
    {
        if (! $accountingPeriod->is_open) {
            return false;
        }

        if ($accountingPeriod->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $accountingPeriod->fiscalYear->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
