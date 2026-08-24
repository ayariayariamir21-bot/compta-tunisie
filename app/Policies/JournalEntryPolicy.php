<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
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
        return $this->canRead($user, $journalEntry->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $this->canOperate($user, $journalEntry->company_id);
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $this->canOperate($user, $journalEntry->company_id);
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $this->canOperate($user, $journalEntry->company_id);
    }

    public function cancel(User $user, JournalEntry $journalEntry): bool
    {
        if (! $journalEntry->isDraft()) {
            return false;
        }

        return $this->canOperate($user, $journalEntry->company_id);
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
