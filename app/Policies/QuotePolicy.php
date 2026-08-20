<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
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
        return $user->companies()->where('companies.id', $quote->company_id)->exists();
    }

    public function create(User $user, Quote $quote): bool
    {
        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function send(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function accept(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::SENT) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function reject(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::SENT) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function cancel(User $user, Quote $quote): bool
    {
        if (! in_array($quote->status, [QuoteStatus::DRAFT, QuoteStatus::SENT], true)) {
            return false;
        }

        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function duplicate(User $user, Quote $quote): bool
    {
        return $user->companies()->where('companies.id', $quote->company_id)->wherePivot('role', 'admin')->exists();
    }
}
