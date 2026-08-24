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
        return $this->canRead($user, $accountingPeriod->fiscalYear->company_id);
    }

    public function update(User $user, AccountingPeriod $accountingPeriod): bool
    {
        if ($accountingPeriod->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $accountingPeriod->fiscalYear->company_id);
    }

    public function close(User $user, AccountingPeriod $accountingPeriod): bool
    {
        if (! $accountingPeriod->is_open) {
            return false;
        }

        if ($accountingPeriod->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $accountingPeriod->fiscalYear->company_id);
    }

    /**
     * Any active member may read company data.
     */
    private function canRead(User $user, int $companyId): bool
    {
        return $user->isActiveCompanyMember($companyId);
    }

    /**
     * Only company admins may change accounting configuration.
     */
    private function canManageConfiguration(User $user, int $companyId): bool
    {
        return $user->isCompanyAdmin($companyId);
    }
}
