<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Centralized, locale-independent formatting for PDF reports.
 *
 * Monetary values are BCMath numeric strings with 3 decimal places and must
 * never pass through floats: grouping and decimal separators are applied on
 * the raw string representation.
 */
final class PdfFormat
{
    /**
     * Format a numeric string as an accounting amount: "12 345,500".
     *
     * Thousands separator: space. Decimal separator: comma. Exactly 3 decimals.
     *
     * @param  numeric-string  $value
     */
    public static function money(string $value): string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new \InvalidArgumentException(sprintf('Valeur numérique invalide pour un montant : %s.', $value));
        }

        $sign = str_starts_with($value, '-') ? '-' : '';
        [$integerPart, $decimalPart] = array_pad(explode('.', ltrim($value, '-')), 2, '');

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integerPart === '' ? '0' : $integerPart) ?? $integerPart;
        $decimals = str_pad(substr($decimalPart.'000', 0, 3), 3, '0');

        return $sign.$grouped.','.$decimals;
    }

    /**
     * Format a date value as dd/mm/YYYY; null or unparseable values yield "—".
     */
    public static function dateFr(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception) {
            return '—';
        }
    }

    /**
     * French display label for an account type value.
     */
    public static function accountType(string $type): string
    {
        return match ($type) {
            'asset' => 'Actif',
            'liability' => 'Passif',
            'equity' => 'Capitaux propres',
            'revenue' => 'Produits',
            'expense' => 'Charges',
            default => ucfirst($type),
        };
    }

    /**
     * French display label for a statement row type.
     *
     * @param  'customer'|'supplier'  $context
     */
    public static function statementType(string $type, string $context): string
    {
        if ($context === 'supplier') {
            return match ($type) {
                'purchase_invoice' => 'Facture fournisseur',
                'credit_note' => 'Avoir fournisseur',
                'supplier_payment' => 'Règlement fournisseur',
                default => ucfirst(str_replace('_', ' ', $type)),
            };
        }

        return match ($type) {
            'invoice' => 'Facture',
            'credit_note' => 'Avoir',
            'payment' => 'Encaissement',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
