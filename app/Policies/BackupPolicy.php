<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Backup;
use App\Models\User;

/**
 * Backups contain the data of every company, so administration is granted
 * by company role held anywhere, not scoped to a single company.
 *
 * Matrix:
 *  - Admin:     view, create, validate, download, restore, delete
 *  - Accountant:view, validate, download (no create, no restore, no delete)
 *  - Viewer:    nothing.
 */
class BackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleAnywhere(CompanyRole::Admin, CompanyRole::Accountant);
    }

    public function view(User $user, Backup $backup): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdminAnywhere();
    }

    public function validate(User $user, Backup $backup): bool
    {
        return $this->viewAny($user);
    }

    public function download(User $user, Backup $backup): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $user->isCompanyAdminAnywhere();
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $user->isCompanyAdminAnywhere();
    }
}
