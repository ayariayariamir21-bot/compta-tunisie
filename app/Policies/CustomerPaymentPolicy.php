<?php

namespace App\Policies;

use App\Enums\CustomerPaymentStatus;
use App\Models\Company;
use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomerPayment $customerPayment): bool
    {
        return $user->companies()->where('companies.id', $customerPayment->company_id)->exists();
    }

    public function create(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->wherePivot('role', 'admin')->exists();
    }

    public function createFromInvoice(User $user, CustomerPayment $customerPayment): bool
    {
        return $user->companies()->where('companies.id', $customerPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, CustomerPayment $customerPayment): bool
    {
        if ($customerPayment->status !== CustomerPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $customerPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, CustomerPayment $customerPayment): bool
    {
        if ($customerPayment->status !== CustomerPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $customerPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, CustomerPayment $customerPayment): bool
    {
        if ($customerPayment->status !== CustomerPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $customerPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, CustomerPayment $customerPayment): bool
    {
        if ($customerPayment->status !== CustomerPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $customerPayment->company_id)->wherePivot('role', 'admin')->exists();
    }
}
