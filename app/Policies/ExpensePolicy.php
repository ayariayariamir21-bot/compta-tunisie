<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\ExpenseStatus;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->canRead($user, $expense->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $expense->company_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $expense->company_id);
    }

    public function post(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $expense->company_id);
    }

    public function cancel(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $expense->company_id);
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
