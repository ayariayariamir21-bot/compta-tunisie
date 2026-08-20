<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::ACCEPTED => 'Accepté',
            self::REJECTED => 'Refusé',
            self::EXPIRED => 'Expiré',
            self::CANCELLED => 'Annulé',
        };
    }
}
