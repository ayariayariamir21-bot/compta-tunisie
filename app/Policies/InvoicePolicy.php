<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canRead($user, $invoice->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $invoice->company_id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $invoice->company_id);
    }

    public function post(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $invoice->company_id);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $invoice->company_id);
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
