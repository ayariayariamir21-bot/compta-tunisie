<?php

namespace App\Enums;

/**
 * Health status of a system component or of the whole application.
 */
enum HealthStatus: string
{
    case Ok = 'ok';

    case Warning = 'warning';

    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Sain',
            self::Warning => 'Avertissement',
            self::Critical => 'Critique',
        };
    }

    /**
     * The worst status among the given ones (critical > warning > ok).
     *
     * @param  list<self>  $statuses
     */
    public static function worst(array $statuses): self
    {
        if (in_array(self::Critical, $statuses, true)) {
            return self::Critical;
        }

        if (in_array(self::Warning, $statuses, true)) {
            return self::Warning;
        }

        return self::Ok;
    }
}
