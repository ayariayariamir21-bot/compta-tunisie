<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\SupplierPaymentStatus;
use App\Models\Company;
use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canRead($user, $supplierPayment->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function createFromInvoice(User $user, SupplierPayment $supplierPayment): bool
    {
        return $this->canOperate($user, $supplierPayment->company_id);
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $supplierPayment->company_id);
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $supplierPayment->company_id);
    }

    public function post(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $supplierPayment->company_id);
    }

    public function cancel(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $supplierPayment->company_id);
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
