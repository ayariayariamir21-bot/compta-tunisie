<?php

namespace App\Services\Security;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

/**
 * Central in-app notification dispatcher.
 *
 * Responsibilities:
 *  - recipient selection (active company memberships by role)
 *  - duplicate protection (identical unread notification short-circuits)
 *  - safe delivery (a failing notification never breaks the business action)
 *  - read-state mutations with server-side ownership enforcement
 *
 * Delivery is synchronous through Laravel's database channel; callers invoke
 * this service AFTER their accounting transaction commits so a rollback can
 * never leave a notification describing an event that did not happen.
 */
final class NotificationService
{
    /**
     * Notify a single user. Failures are reported but swallowed so the
     * triggering business action is unaffected.
     */
    public function notifyUser(User $user, AppNotification $notification): void
    {
        try {
            if ($this->hasUnreadDuplicate($user, $notification)) {
                return;
            }

            $user->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  iterable<User>  $users
     */
    public function notifyUsers(iterable $users, AppNotification $notification): void
    {
        foreach ($users as $user) {
            $this->notifyUser($user, $notification);
        }
    }

    /**
     * Notify every ACTIVE member of the company holding one of the given
     * roles. Inactive memberships are never notified. An optional user id
     * (typically the actor) is excluded to avoid self-notification spam.
     *
     * @param  list<CompanyRole>  $roles
     */
    public function notifyCompanyRoles(
        Company $company,
        array $roles,
        AppNotification $notification,
        ?int $exceptUserId = null,
    ): void {
        if ($roles === []) {
            return;
        }

        $recipients = $company->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', array_map(fn (CompanyRole $role) => $role->value, $roles))
            ->when($exceptUserId !== null, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get();

        $this->notifyUsers($recipients, $notification);
    }

    /**
     * Mark one notification as read. Ownership is enforced here: only
     * notifications belonging to the given user are ever considered, and
     * ids are matched against the user's own notifications relation.
     */
    public function markAsRead(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (! $notification instanceof DatabaseNotification) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return true;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    /**
     * @return int<0, max>
     */
    public function unreadCount(User $user): int
    {
        return (int) $user->unreadNotifications()->count();
    }

    private function hasUnreadDuplicate(User $user, AppNotification $notification): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $user->id)
            ->where('type', $notification::class)
            ->whereNull('read_at')
            ->where('data->dedup_key', $notification->deduplicationKey())
            ->exists();
    }
}
