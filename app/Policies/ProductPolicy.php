<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->exists();
    }

    public function create(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function activate(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->wherePivot('role', 'admin')->exists();
    }

    public function deactivate(User $user, Product $product): bool
    {
        return $user->companies()->where('companies.id', $product->company_id)->wherePivot('role', 'admin')->exists();
    }
}
