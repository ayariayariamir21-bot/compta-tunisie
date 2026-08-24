<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->canRead($user, $quote->company_id);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canOperate($user, $company->id);
    }

    public function update(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function delete(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function send(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function accept(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::SENT) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function reject(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::SENT) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function cancel(User $user, Quote $quote): bool
    {
        if (! in_array($quote->status, [QuoteStatus::DRAFT, QuoteStatus::SENT], true)) {
            return false;
        }

        return $this->canOperate($user, $quote->company_id);
    }

    public function duplicate(User $user, Quote $quote): bool
    {
        return $this->canOperate($user, $quote->company_id);
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
