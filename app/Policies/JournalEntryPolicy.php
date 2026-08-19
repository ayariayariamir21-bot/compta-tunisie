<?php

namespace App\Policies;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->exists();
    }

    public function create(User $user, JournalEntry $journalEntry): bool
    {
        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        if ($journalEntry->status !== JournalEntryStatus::DRAFT) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function cancel(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $user->companies()
            ->where('companies.id', $journalEntry->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
