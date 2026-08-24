<?php

namespace App\Notifications;

use App\Enums\NotificationType;

/**
 * Business posting events: sales invoice, customer payment, purchase
 * invoice and supplier payment posted. Emitted once per meaningful state
 * transition, after the accounting transaction commits.
 */
final class BusinessNotification extends AppNotification
{
    public function type(): NotificationType
    {
        return NotificationType::Business;
    }
}
