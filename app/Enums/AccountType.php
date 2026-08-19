<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Actif',
            self::Liability => 'Passif',
            self::Equity => 'Capitaux propres',
            self::Revenue => 'Produits',
            self::Expense => 'Charges',
        };
    }
}
