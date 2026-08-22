<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::POSTED => 'Comptabilisé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'amber',
            self::POSTED => 'green',
            self::CANCELLED => 'red',
        };
    }
}
