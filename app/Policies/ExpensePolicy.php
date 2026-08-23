<?php

namespace App\Policies;

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
        return $user->companies()->where('companies.id', $expense->company_id)->exists();
    }

    public function create(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $expense->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $expense->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $expense->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, Expense $expense): bool
    {
        if ($expense->status !== ExpenseStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $expense->company_id)->wherePivot('role', 'admin')->exists();
    }
}
