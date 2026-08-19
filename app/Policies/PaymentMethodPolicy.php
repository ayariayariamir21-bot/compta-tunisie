<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->exists();
    }

    public function create(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function activate(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function deactivate(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    public function setDefault(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->companies()
            ->where('companies.id', $paymentMethod->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
