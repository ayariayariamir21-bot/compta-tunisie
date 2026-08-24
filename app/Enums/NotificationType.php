<?php

namespace App\Enums;

/**
 * Controlled category of in-app notifications. Only categories actually
 * emitted by the application are declared.
 */
enum NotificationType: string
{
    case Security = 'security';

    case Accounting = 'accounting';

    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Security => 'Sécurité',
            self::Accounting => 'Comptabilité',
            self::Business => 'Gestion',
        };
    }
}
