<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Security\AuditLogService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages company memberships (company_user pivot) with safety guarantees:
 * a company must always keep at least one active administrator.
 */
class CompanyMembershipService
{
    public function __construct(
        private ?AuditLogService $auditLog = null,
    ) {}

    private function audits(): AuditLogService
    {
        return $this->auditLog ?? new AuditLogService;
    }

    /**
     * Assign or change the role of a member.
     */
    public function changeRole(Company $company, User $member, CompanyRole $role): void
    {
        $this->assertMembershipExists($company, $member);

        DB::transaction(function () use ($company, $member, $role): void {
            $membership = $this->lockMembership($company, $member);

            $isCurrentlyActiveAdmin = $membership->role === CompanyRole::Admin->value
                && (bool) $membership->is_active;

            if ($isCurrentlyActiveAdmin && $role !== CompanyRole::Admin) {
                $this->assertOtherActiveAdminExists($company, $member);
            }

            $previousRole = CompanyRole::from($membership->role);

            DB::table('company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $member->id)
                ->update([
                    'role' => $role->value,
                    'updated_at' => now(),
                ]);

            if ($previousRole !== $role) {
                $this->audits()->log(
                    AuditAction::RoleChanged,
                    company: $company,
                    entity: $member,
                    before: ['member_email' => $member->email, 'role' => $previousRole->label()],
                    after: ['member_email' => $member->email, 'role' => $role->label()],
                );
            }
        });
    }

    /**
     * Revoke access without deleting the membership row.
     */
    public function deactivate(Company $company, User $member): void
    {
        $this->deactivateOrRemove($company, $member, remove: false);
    }

    /**
     * Restore access for a deactivated member.
     */
    public function activate(Company $company, User $member): void
    {
        $this->assertMembershipExists($company, $member);

        DB::transaction(function () use ($company, $member): void {
            DB::table('company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $member->id)
                ->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

            $this->audits()->logAction(
                AuditAction::MemberActivated,
                "Membre réactivé : {$member->email}.",
                company: $company,
                entity: $member,
            );
        });
    }

    /**
     * Detach the member from the company entirely.
     */
    public function remove(Company $company, User $member): void
    {
        $this->deactivateOrRemove($company, $member, remove: true);
    }

    private function deactivateOrRemove(Company $company, User $member, bool $remove): void
    {
        $this->assertMembershipExists($company, $member);

        DB::transaction(function () use ($company, $member, $remove): void {
            $membership = $this->lockMembership($company, $member);

            $isActiveAdmin = $membership->role === CompanyRole::Admin->value
                && (bool) $membership->is_active;

            if ($isActiveAdmin) {
                $this->assertOtherActiveAdminExists($company, $member);
            }

            if ($remove) {
                DB::table('company_user')
                    ->where('company_id', $company->id)
                    ->where('user_id', $member->id)
                    ->delete();

                $this->audits()->logAction(
                    AuditAction::MemberRemoved,
                    "Membre retiré : {$member->email}.",
                    company: $company,
                    entity: $member,
                );

                return;
            }

            DB::table('company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $member->id)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            $this->audits()->logAction(
                AuditAction::MemberDeactivated,
                "Membre désactivé : {$member->email}.",
                company: $company,
                entity: $member,
            );
        });
    }

    private function assertMembershipExists(Company $company, User $member): void
    {
        $exists = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->exists();

        if (! $exists) {
            throw new RuntimeException("L'utilisateur n'est pas membre de cette société.");
        }
    }

    /**
     * @return object{role: string, is_active: bool}
     */
    private function lockMembership(Company $company, User $member): object
    {
        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw new RuntimeException("L'utilisateur n'est pas membre de cette société.");
        }

        /** @var object{role: string, is_active: bool} $membership */
        return $membership;
    }

    private function assertOtherActiveAdminExists(Company $company, User $exceptMember): void
    {
        $otherAdmins = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', '!=', $exceptMember->id)
            ->where('role', CompanyRole::Admin->value)
            ->where('is_active', true)
            ->count();

        if ($otherAdmins < 1) {
            throw new InvalidArgumentException('Au moins un administrateur actif doit rester dans la société.');
        }
    }
}
