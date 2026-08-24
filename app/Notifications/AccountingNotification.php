<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * Accounting events: fiscal year closure and accounting period closure.
 * Emitted once per meaningful state transition to company admins and
 * accountants.
 */
final class AccountingNotification extends AppNotification
{
    public function type(): NotificationType
    {
        return NotificationType::Accounting;
    }
}
