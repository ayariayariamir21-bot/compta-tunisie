<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
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
        return $user->companies()->where('companies.id', $invoice->company_id)->exists();
    }

    public function create(User $user, Invoice $invoice): bool
    {
        return $user->companies()->where('companies.id', $invoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $invoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $invoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $invoice->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $invoice->company_id)->wherePivot('role', 'admin')->exists();
    }
}
