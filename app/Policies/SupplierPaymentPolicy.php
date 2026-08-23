<?php

namespace App\Policies;

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
        return $user->companies()->where('companies.id', $supplierPayment->company_id)->exists();
    }

    public function create(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->wherePivot('role', 'admin')->exists();
    }

    public function createFromInvoice(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->companies()->where('companies.id', $supplierPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $supplierPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $supplierPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $supplierPayment->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, SupplierPayment $supplierPayment): bool
    {
        if ($supplierPayment->status !== SupplierPaymentStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $supplierPayment->company_id)->wherePivot('role', 'admin')->exists();
    }
}
