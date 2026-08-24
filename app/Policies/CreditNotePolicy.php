<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->canRead($user, $creditNote->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function createFromInvoice(User $user, CreditNote $creditNote): bool
    {
        return $this->canOperate($user, $creditNote->company_id);
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $creditNote->company_id);
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $creditNote->company_id);
    }

    public function post(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $creditNote->company_id);
    }

    public function cancel(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $creditNote->company_id);
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
