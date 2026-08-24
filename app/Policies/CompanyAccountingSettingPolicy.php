<?php

namespace App\Policies;

use App\Models\CompanyAccountingSetting;
use App\Models\User;

class CompanyAccountingSettingPolicy
{
    public function view(User $user, CompanyAccountingSetting $setting): bool
    {
        return $this->canRead($user, $setting->company_id);
    }

    public function update(User $user, CompanyAccountingSetting $setting): bool
    {
        return $this->canManageConfiguration($user, $setting->company_id);
    }

    /**
     * Any active member may read company data.
     */
    private function canRead(User $user, int $companyId): bool
    {
        return $user->isActiveCompanyMember($companyId);
    }

    /**
     * Only company admins may change accounting configuration.
     */
    private function canManageConfiguration(User $user, int $companyId): bool
    {
        return $user->isCompanyAdmin($companyId);
    }
}
