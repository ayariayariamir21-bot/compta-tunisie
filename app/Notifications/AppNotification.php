<?php

namespace App\Notifications;

use App\Enums\NotificationSeverity;
use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

/**
 * Base class for in-app (database channel) notifications.
 *
 * Payload contract consumed by the notification UI:
 *  - title / message / severity / type for display
 *  - company_id for context labeling (validated server-side, never trusted)
 *  - entity_type / entity_id for traceability
 *  - link (route name + parameters) resolved and re-authorized server-side
 *    before navigation; possessing a notification never grants access.
 *
 * Deliberately synchronous (no ShouldQueue): the database channel insert is
 * cheap, so security notifications never depend on a queue worker. Callers
 * dispatch after their transaction commits; delivery failures are reported
 * but must never break the business action that produced them.
 */
abstract class AppNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int|string>  $routeParams
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly NotificationSeverity $severity,
        public readonly string $dedupKey,
        public readonly ?int $companyId = null,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly ?string $routeName = null,
        public readonly array $routeParams = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    abstract public function type(): NotificationType;

    /**
     * Stable identity used by duplicate protection: an identical unread
     * notification for the same recipient short-circuits creation.
     */
    public function deduplicationKey(): string
    {
        return $this->dedupKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type()->value,
            'severity' => $this->severity->value,
            'company_id' => $this->companyId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'link' => $this->routeName !== null ? [
                'route' => $this->routeName,
                'params' => $this->routeParams,
            ] : null,
            'dedup_key' => $this->deduplicationKey(),
        ];
    }

    /**
     * Resolve the stored payload into a display-ready URL when the route
     * still exists; null otherwise.
     */
    public static function resolveLink(DatabaseNotification $notification): ?string
    {
        $link = $notification->data['link'] ?? null;

        if (! is_array($link) || ! is_string($link['route'] ?? null)) {
            return null;
        }

        try {
            return route($link['route'], is_array($link['params'] ?? null) ? $link['params'] : []);
        } catch (\Throwable) {
            return null;
        }
    }
}
