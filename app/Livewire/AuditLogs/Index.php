<?php

namespace App\Livewire\AuditLogs;

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $action = '';

    public string $userId = '';

    public string $entityType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 25;

    public ?int $selectedLogId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        if (! $this->authorizedUser($currentCompany, [CompanyRole::Admin, CompanyRole::Accountant])) {
            abort(403);
        }
    }

    public function showDetail(int $logId): void
    {
        $company = app(CurrentCompany::class)->get(Auth::user());
        $user = Auth::user();

        if ($company === null || ! $user instanceof User) {
            abort(403);
        }

        $auditLog = AuditLog::where('company_id', $company->id)->find($logId);

        if ($auditLog === null || $user->cannot('view', $auditLog)) {
            abort(403);
        }

        $this->selectedLogId = $logId;
    }

    public function closeDetail(): void
    {
        $this->selectedLogId = null;
    }

    public function render(CurrentCompany $currentCompany): View
    {
        if (! $this->authorizedUser($currentCompany, [CompanyRole::Admin, CompanyRole::Accountant])) {
            abort(403);
        }

        /** @var User $user */
        $user = Auth::user();

        $company = $currentCompany->get($user);

        $isAccountantOnly = ! $user->isCompanyAdmin($company)
            && $user->hasCompanyRole($company, CompanyRole::Accountant);

        $sensitiveActions = array_map(
            fn (AuditAction $case): string => $case->value,
            array_values(array_filter(AuditAction::cases(), fn (AuditAction $case): bool => $case->isSecuritySensitive())),
        );

        $query = AuditLog::query()
            ->where('company_id', $company->id)
            ->with('user');

        if ($isAccountantOnly) {
            $query->whereNotIn('action', $sensitiveActions);
        }

        if ($this->action !== '') {
            $query->where('action', $this->action);
        }

        if ($this->userId !== '') {
            $query->where('user_id', (int) $this->userId);
        }

        if ($this->entityType !== '') {
            $query->where('entity_type', $this->entityType);
        }

        if ($this->dateFrom !== '' && strtotime($this->dateFrom) !== false) {
            $query->where('created_at', '>=', $this->dateFrom.' 00:00:00');
        }

        if ($this->dateTo !== '' && strtotime($this->dateTo) !== false) {
            $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';

            $query->where(function ($q) use ($term): void {
                $q->where('description', 'like', $term)
                    ->orWhere('entity_type', 'like', $term)
                    ->orWhere('ip_address', 'like', $term);
            });
        }

        return view('livewire.audit-logs.index', [
            'logs' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate($this->perPage),
            'actions' => collect(AuditAction::cases())
                ->reject(fn (AuditAction $case): bool => $isAccountantOnly && $case->isSecuritySensitive())
                ->map(fn (AuditAction $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->all(),
            'users' => $this->companyUsers($company),
            'entityTypes' => AuditLog::query()
                ->where('company_id', $company->id)
                ->whereNotNull('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type')
                ->all(),
            'selected' => $this->selectedLog(),
        ]);
    }

    /**
     * @param  list<CompanyRole>  $roles
     */
    private function authorizedUser(CurrentCompany $currentCompany, array $roles): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $company = $currentCompany->get($user);

        return $company !== null && $user->hasAnyCompanyRole($company, ...$roles);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedLog(): ?array
    {
        if ($this->selectedLogId === null) {
            return null;
        }

        $company = app(CurrentCompany::class)->get(Auth::user());
        $user = Auth::user();

        if ($company === null || ! $user instanceof User) {
            return null;
        }

        $auditLog = AuditLog::with('user')->where('company_id', $company->id)->find($this->selectedLogId);

        if ($auditLog === null || $user->cannot('view', $auditLog)) {
            return null;
        }

        $action = AuditAction::tryFrom($auditLog->action);
        $actor = $auditLog->getRelationValue('user');

        return [
            'id' => $auditLog->id,
            'action_label' => $action?->label() ?? $auditLog->action,
            'description' => $auditLog->description,
            'entity' => $auditLog->entity_type !== null ? $auditLog->entity_type.' #'.$auditLog->entity_id : null,
            'user_name' => $actor instanceof User ? $actor->name : __('Système'),
            'user_email' => $actor instanceof User ? $actor->email : null,
            'ip_address' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
            'route' => $auditLog->route,
            'method' => $auditLog->method,
            'created_at' => $auditLog->created_at?->format('d/m/Y H:i:s'),
            'before_json' => $auditLog->before_data !== null ? json_encode($auditLog->before_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $auditLog->after_data !== null ? json_encode($auditLog->after_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'metadata_json' => $auditLog->metadata !== null ? json_encode($auditLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function companyUsers(Company $company): array
    {
        $rows = DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->where('company_user.company_id', $company->id)
            ->where('company_user.is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);

        /** @var list<array{id: int, name: string}> $users */
        $users = [];

        foreach ($rows as $row) {
            $users[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ];
        }

        return $users;
    }
}
