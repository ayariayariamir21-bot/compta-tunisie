<?php

namespace App\Policies;

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
        return $user->companies()->where('companies.id', $creditNote->company_id)->exists();
    }

    public function create(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->wherePivot('role', 'admin')->exists();
    }

    public function createFromInvoice(User $user, CreditNote $creditNote): bool
    {
        return $user->companies()->where('companies.id', $creditNote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $creditNote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $creditNote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function post(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $creditNote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, CreditNote $creditNote): bool
    {
        if ($creditNote->status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $creditNote->company_id)->wherePivot('role', 'admin')->exists();
    }
}
