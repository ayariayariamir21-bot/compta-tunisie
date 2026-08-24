<?php

namespace App\Livewire\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Security\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Header notification bell: latest notifications for the authenticated user
 * plus the unread count. Deliberately lightweight — a fixed small window of
 * recent rows and one count query; the full history lives on the index page.
 */
final class Dropdown extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAsRead(NotificationService $notifications, string $id): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            // Ownership enforced server-side through the user's own
            // notifications relation.
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
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        return $user->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(function ($notification): array {
                $data = $notification->data;

                return [
                    'id' => (string) $notification->id,
                    'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
                    'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
                    'severity' => is_string($data['severity'] ?? null) ? $data['severity'] : 'info',
                    'type_label' => NotificationType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '')?->label() ?? '',
                    'read' => $notification->read_at !== null,
                    'date' => optional($notification->created_at)?->format('d/m/Y H:i'),
                    'url' => AppNotification::resolveLink($notification),
                ];
            })
            ->all();
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        $unread = 0;

        if ($user instanceof User) {
            $unread = app(NotificationService::class)->unreadCount($user);
        }

        return view('livewire.notifications.dropdown', [
            'items' => $this->items(),
            'unreadCount' => $unread,
        ]);
    }
}
