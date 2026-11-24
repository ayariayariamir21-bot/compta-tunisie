<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public const SCALE = 3;

    /**
     * Normalize a monetary value to a fixed 3-decimal numeric string without float conversion.
     *
     * @return numeric-string
     */
    public static function normalize(string|int|null $value): string
    {
        $value = trim((string) ($value ?? '0'));

        if (! preg_match('/^-?\d+(?:\.\d{1,3})?$/', $value)) {
            throw new InvalidArgumentException('Le montant doit être un décimal valide avec au plus trois décimales.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $normalized = ($negative ? '-' : '').$whole.'.'.str_pad($fraction, self::SCALE, '0');

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('Le montant doit être un décimal valide avec au plus trois décimales.');
        }

        return $normalized;
    }

    /**
     * Round any scalar (database aggregate, user input) to 3 decimals, half away from zero.
     *
     * Floats only appear at the database boundary (e.g. SQLite SUM()), so they are
     * formatted once here; strings are rounded with BCMath to avoid float drift.
     *
     * @return numeric-string
     */
    public static function round(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        if (is_float($value)) {
            return self::normalize(number_format($value, self::SCALE, '.', ''));
        }

        $value = str_replace(',', '', trim((string) $value));

        if (! is_numeric($value) || str_contains(strtolower($value), 'e')) {
            throw new InvalidArgumentException('Le montant doit être un décimal valide.');
        }

        $offset = str_starts_with($value, '-') ? '-0.0005' : '0.0005';

        return self::normalize(bcadd($value, $offset, self::SCALE));
    }
}
