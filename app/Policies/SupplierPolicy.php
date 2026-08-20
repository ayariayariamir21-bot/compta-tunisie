<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->exists();
    }

    public function create(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function activate(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function deactivate(User $user, Supplier $supplier): bool
    {
        return $user->companies()->where('companies.id', $supplier->company_id)->wherePivot('role', 'admin')->exists();
    }
}
