<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->canRead($user, $product->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canOperate($user, $product->company_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->canOperate($user, $product->company_id);
    }

    public function activate(User $user, Product $product): bool
    {
        return $this->canOperate($user, $product->company_id);
    }

    public function deactivate(User $user, Product $product): bool
    {
        return $this->canOperate($user, $product->company_id);
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
