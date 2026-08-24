<?php

namespace App\Enums;

/**
 * Display severity of in-app notifications.
 */
enum NotificationSeverity: string
{
    case Info = 'info';

    case Success = 'success';

    case Warning = 'warning';

    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Success => 'Succès',
            self::Warning => 'Avertissement',
            self::Danger => 'Critique',
        };
    }
}
