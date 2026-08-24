<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->canRead($user, $account->company_id);
    }

    public function create(User $user, Company $company): bool
    {

        return $this->canManageConfiguration($user, $company->id);
    }

    public function update(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $account->company_id);
    }

    public function delete(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        if ($account->hasChildren()) {
            return false;
        }

        return $this->canManageConfiguration($user, $account->company_id);
    }

    public function activate(User $user, Account $account): bool
    {
        if ($account->fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $account->company_id);
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
