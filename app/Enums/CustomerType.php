<?php

namespace App\Enums;

enum CustomerType: string
{
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
    case PUBLIC_ENTITY = 'public_entity';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Particulier',
            self::COMPANY => 'Société',
            self::PUBLIC_ENTITY => 'Organisme public',
            self::OTHER => 'Autre',
        };
    }
}
