<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case CARD = 'card';
    case DIRECT_DEBIT = 'direct_debit';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Espèces',
            self::BANK_TRANSFER => 'Virement bancaire',
            self::CHEQUE => 'Chèque',
            self::CARD => 'Carte bancaire',
            self::DIRECT_DEBIT => 'Prélèvement',
            self::OTHER => 'Autre',
        };
    }
}
