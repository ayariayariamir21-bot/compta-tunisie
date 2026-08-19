<?php

namespace App\Policies;

use App\Models\Journal;
use App\Models\User;

class JournalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Journal $journal): bool
    {
        return $user->companies()
            ->where('companies.id', $journal->company_id)
            ->exists();
    }

    public function create(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journal->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journal->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journal->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, Journal $journal): bool
    {
        if ($journal->fiscalYear->is_closed) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journal->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
