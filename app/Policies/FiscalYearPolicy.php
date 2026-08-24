<?php

namespace App\Policies;

use App\Models\Company;
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
        return $this->canRead($user, $fiscalYear->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageConfiguration($user, $company->id);
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $fiscalYear->company_id);
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $fiscalYear->company_id);
    }

    public function activate(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->canManageConfiguration($user, $fiscalYear->company_id);
    }

    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        if ($fiscalYear->is_closed) {
            return false;
        }

        return $this->canManageConfiguration($user, $fiscalYear->company_id);
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
