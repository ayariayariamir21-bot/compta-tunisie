<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Journal;
use App\Models\User;

class JournalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Journal $journal): bool
    {
        return $this->canRead($user, $journal->company_id);
    }

    public function create(User $user, Company $company): bool
    {

        return $this->canManageConfiguration($user, $company->id);
    }

    public function update(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $journal->company_id);
    }

    public function delete(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $journal->company_id);
    }

    public function activate(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $journal->company_id);
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
