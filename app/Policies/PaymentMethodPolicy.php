<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canRead($user, $paymentMethod->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageConfiguration($user, $company->id);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canManageConfiguration($user, $paymentMethod->company_id);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canManageConfiguration($user, $paymentMethod->company_id);
    }

    public function activate(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canManageConfiguration($user, $paymentMethod->company_id);
    }

    public function deactivate(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canManageConfiguration($user, $paymentMethod->company_id);
    }

    public function setDefault(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->canManageConfiguration($user, $paymentMethod->company_id);
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
