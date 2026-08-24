<?php

namespace App\Policies;

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can access the audit journal of a company.
     *
     * Laravel strips the string class name from gate arguments like
     * [AuditLog::class, $company], so the remaining argument may be the
     * company itself, an audit log row, or a raw company id.
     */
    public function viewAny(User $user, Company|int|AuditLog $context): bool
    {
        $companyId = match (true) {
            $context instanceof Company => (int) $context->getKey(),
            $context instanceof AuditLog => $context->company_id === null ? null : (int) $context->company_id,
            default => $context,
        };

        if ($companyId === null) {
            return false;
        }

        return $user->hasAnyCompanyRole($companyId, CompanyRole::Admin, CompanyRole::Accountant);
    }

    /**
     * Admins see everything; accountants only business and accounting
     * activity; viewers and outsiders nothing.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($auditLog->company_id === null || ! $user->isActiveCompanyMember($auditLog->company_id)) {
            return false;
        }

        if ($user->isCompanyAdmin($auditLog->company_id)) {
            return true;
        }

        if (! $user->hasCompanyRole($auditLog->company_id, CompanyRole::Accountant)) {
            return false;
        }

        return ! AuditAction::tryFrom($auditLog->action)?->isSecuritySensitive();
    }
}
