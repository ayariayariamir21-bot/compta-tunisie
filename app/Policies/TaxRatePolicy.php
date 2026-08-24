<?php

namespace App\Policies;

use App\Models\Company;
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
        return $this->canRead($user, $taxRate->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageConfiguration($user, $company->id);
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->canManageConfiguration($user, $taxRate->company_id);
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->canManageConfiguration($user, $taxRate->company_id);
    }

    public function activate(User $user, TaxRate $taxRate): bool
    {
        return $this->canManageConfiguration($user, $taxRate->company_id);
    }

    public function deactivate(User $user, TaxRate $taxRate): bool
    {
        return $this->canManageConfiguration($user, $taxRate->company_id);
    }

    public function setDefault(User $user, TaxRate $taxRate): bool
    {
        return $this->canManageConfiguration($user, $taxRate->company_id);
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
