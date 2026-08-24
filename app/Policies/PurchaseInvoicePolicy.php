<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Company;
use App\Models\PurchaseInvoice;
use App\Models\User;

class PurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        return $this->canRead($user, $purchaseInvoice->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $purchaseInvoice->company_id);
    }

    public function delete(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $purchaseInvoice->company_id);
    }

    public function post(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $purchaseInvoice->company_id);
    }

    public function cancel(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $purchaseInvoice->company_id);
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
