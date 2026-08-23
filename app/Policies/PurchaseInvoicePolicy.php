<?php

namespace App\Policies;

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
        return $user->companies()->where('companies.id', $purchaseInvoice->company_id)->exists();
    }

    public function create(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $purchaseInvoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $purchaseInvoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $purchaseInvoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, PurchaseInvoice $purchaseInvoice): bool
    {
        if ($purchaseInvoice->status !== PurchaseInvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $purchaseInvoice->company_id)->wherePivot('role', 'admin')->exists();
    }
}
