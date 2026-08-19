<?php

namespace App\Enums;

enum JournalType: string
{
    case ACHATS = 'achats';
    case VENTES = 'ventes';
    case BANQUE = 'banque';
    case CAISSE = 'caisse';
    case OPERATIONS_DIVERSES = 'operations_diverses';

    public function label(): string
    {
        return match ($this) {
            self::ACHATS => 'Achats',
            self::VENTES => 'Ventes',
            self::BANQUE => 'Banque',
            self::CAISSE => 'Caisse',
            self::OPERATIONS_DIVERSES => 'Opérations diverses',
        };
    }
}
