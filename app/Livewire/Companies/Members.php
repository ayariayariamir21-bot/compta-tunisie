<?php

namespace App\Livewire\Companies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class Members extends Component
{
    /** @var list<array{id: int, name: string, email: string, role: string, is_active: bool, is_last_admin: bool}> */
    public array $members = [];

    #[Locked]
    public ?int $companyId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get(Auth::user());

        if (! $company || Auth::user()->cannot('manageMembers', $company)) {
            abort(403);
        }

        $this->companyId = $company->id;

        $this->loadMembers();
    }

    public function changeRole(CompanyMembershipService $memberships, int $memberId, string $role): void
    {
        $this->authorizeManagement();

        $newRole = $this->validatedRole($role);
        if ($newRole === null) {
            session()->flash('error', 'Rôle invalide.');

            return;
        }

        $member = User::findOrFail($memberId);

        try {
            $memberships->changeRole($this->currentCompany(), $member, $newRole);

            session()->flash('success', "Le rôle de {$member->name} a été mis à jour.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->loadMembers();
    }

    public function deactivateMember(CompanyMembershipService $memberships, int $memberId): void
    {
        $this->authorizeManagement();

        try {
            $memberships->deactivate($this->currentCompany(), User::findOrFail($memberId));

            session()->flash('success', "L'accès du membre a été révoqué.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->loadMembers();
    }

    public function activateMember(CompanyMembershipService $memberships, int $memberId): void
    {
        $this->authorizeManagement();

        try {
            $memberships->activate($this->currentCompany(), User::findOrFail($memberId));

            session()->flash('success', "L'accès du membre a été restauré.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->loadMembers();
    }

    public function removeMember(CompanyMembershipService $memberships, int $memberId): void
    {
        $this->authorizeManagement();

        try {
            $memberships->remove($this->currentCompany(), User::findOrFail($memberId));

            session()->flash('success', 'Le membre a été retiré de la société.');
        } catch (InvalidArgumentException|RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->loadMembers();
    }

    private function authorizeManagement(): void
    {
        if (! $this->companyId || Auth::user()->cannot('manageMembers', $this->currentCompany())) {
            abort(403);
        }
    }

    private function validatedRole(string $role): ?CompanyRole
    {
        $value = strtolower(trim($role));

        foreach (CompanyRole::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }

    private function currentCompany(): Company
    {
        return Company::findOrFail((int) $this->companyId);
    }

    private function loadMembers(): void
    {
        $company = $this->currentCompany();
        $activeAdminCount = $this->activeAdminCount($company);

        $rows = DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->where('company_user.company_id', $company->id)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'company_user.role',
                'company_user.is_active',
            ]);

        $this->members = [];

        foreach ($rows as $row) {
            $this->members[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'role' => $row->role === null ? CompanyRole::Accountant->value : (string) $row->role,
                'is_active' => (bool) $row->is_active,
                'is_last_admin' => $row->role === CompanyRole::Admin->value
                    && (bool) $row->is_active
                    && $activeAdminCount <= 1,
            ];
        }
    }

    private function activeAdminCount(Company $company): int
    {
        return (int) DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('role', CompanyRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }

    public function render(): View
    {
        $this->authorizeManagement();

        return view('livewire.companies.members');
    }
}
