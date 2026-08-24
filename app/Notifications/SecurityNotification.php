<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * Security events: role changes, membership activation state, 2FA changes.
 * Payloads contain no credentials, secrets or codes.
 */
final class SecurityNotification extends AppNotification
{
    public function type(): NotificationType
    {
        return NotificationType::Security;
    }
}
