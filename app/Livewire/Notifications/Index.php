<?php

namespace App\Livewire\Notifications;

use App\Enums\NotificationSeverity;
use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Security\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notifications')]
final class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $state = 'all';

    #[Url]
    public string $type = '';

    #[Url]
    public string $company = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Mark one notification as read; ownership is resolved server-side.
     */
    public function markAsRead(NotificationService $notifications, string $id): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $notifications->markAsRead($user, $id);
        }
    }

    public function markAllAsRead(NotificationService $notifications): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $notifications->markAllAsRead($user);
        }
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function membershipCompanies(User $user): array
    {
        return $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('name')
            ->get(['companies.id', 'companies.name'])
            ->map(fn ($company): array => ['id' => (int) $company->id, 'name' => (string) $company->name])
            ->all();
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $query = $user->notifications()->latest();

        if ($this->state === 'unread') {
            $query->whereNull('read_at');
        } elseif ($this->state === 'read') {
            $query->whereNotNull('read_at');
        }

        // Filters operate on the JSON payload columns server-side.
        if ($this->type !== '') {
            $query->where('data->type', $this->type);
        }

        if ($this->company !== '') {
            $query->where('data->company_id', (int) $this->company);
        }

        $rows = $query->paginate(15)->through(function ($notification): array {
            $data = $notification->data;

            return [
                'id' => (string) $notification->id,
                'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
                'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
                'severity' => NotificationSeverity::tryFrom(is_string($data['severity'] ?? null) ? $data['severity'] : '') ?? NotificationSeverity::Info,
                'type' => NotificationType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : ''),
                'read' => $notification->read_at !== null,
                'created_at' => optional($notification->created_at)?->format('d/m/Y H:i:s'),
                'url' => AppNotification::resolveLink($notification),
            ];
        });

        return view('livewire.notifications.index', [
            'notifications' => $rows,
            'unreadCount' => app(NotificationService::class)->unreadCount($user),
            'types' => array_map(
                fn (NotificationType $type): array => ['value' => $type->value, 'label' => $type->label()],
                NotificationType::cases(),
            ),
            'companies' => $this->membershipCompanies($user),
        ]);
    }
}
