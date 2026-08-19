<?php

namespace App\Enums;

enum TaxType: string
{
    case VAT = 'vat';
    case EXEMPT = 'exempt';
    case ZERO_RATED = 'zero_rated';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VAT => 'TVA',
            self::EXEMPT => 'Exonéré',
            self::ZERO_RATED => 'Taux zéro',
            self::OTHER => 'Autre',
        };
    }
}
