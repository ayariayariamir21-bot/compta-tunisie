<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
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
        return $this->canRead($user, $customer->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canOperate($user, $customer->company_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canOperate($user, $customer->company_id);
    }

    public function activate(User $user, Customer $customer): bool
    {
        return $this->canOperate($user, $customer->company_id);
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $this->canOperate($user, $customer->company_id);
    }

    /**
     * Any active member may read company data.
     */
    private function canRead(User $user, int $companyId): bool
    {
        return $user->isActiveCompanyMember($companyId);
    }

    /**
     * Admins and accountants may perform operational accounting mutations.
     */
    private function canOperate(User $user, int $companyId): bool
    {
        return $user->hasAnyCompanyRole($companyId, ...CompanyRole::operationalRoles());
    }
}
