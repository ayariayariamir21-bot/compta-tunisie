<?php

namespace App\Enums;

/**
 * Company-scoped role stored on the company_user pivot.
 *
 * A user may hold different roles in different companies; authorization is
 * always resolved through the active membership of the relevant company.
 */
enum CompanyRole: string
{
    case Admin = 'admin';

    case Accountant = 'accountant';

    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Accountant => 'Comptable',
            self::Viewer => 'Lecteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Gestion complète de la société : membres, configuration comptable et toutes les opérations.',
            self::Accountant => 'Opérations comptables courantes : tiers, factures, paiements, écritures et rapports.',
            self::Viewer => 'Consultation seule : données et rapports en lecture, aucune modification.',
        };
    }

    /**
     * Roles allowed to perform operational accounting mutations
     * (tiers, documents, payments, journal entries).
     *
     * @return array{self, self}
     */
    public static function operationalRoles(): array
    {
        return [self::Admin, self::Accountant];
    }
}
