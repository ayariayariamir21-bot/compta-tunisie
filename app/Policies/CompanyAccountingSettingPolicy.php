<?php

namespace App\Policies;

use App\Models\CompanyAccountingSetting;
use App\Models\User;

class CompanyAccountingSettingPolicy
{
    public function view(User $user, CompanyAccountingSetting $setting): bool
    {
        return $user->companies()
            ->where('companies.id', $setting->company_id)
            ->exists();
    }

    public function update(User $user, CompanyAccountingSetting $setting): bool
    {
        return $user->companies()
            ->where('companies.id', $setting->company_id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
