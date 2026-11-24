<?php

declare(strict_types=1);

namespace App\Support;

/**
 * French spelling of TND amounts for legal documents:
 * "1250.500" → "mille deux cent cinquante dinars et cinq cents millimes".
 */
final class AmountInWords
{
    private const UNITS = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    public static function dinars(string $amount): string
    {
        $normalized = Decimal::normalize($amount);
        $negative = str_starts_with($normalized, '-');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));

        $dinars = (int) $whole;
        $millimes = (int) $fraction;

        $words = self::number($dinars).' '.($dinars > 1 ? 'dinars' : 'dinar');

        if ($millimes > 0) {
            $words .= ' et '.self::number($millimes).' '.($millimes > 1 ? 'millimes' : 'millime');
        }

        return ($negative ? 'moins ' : '').$words;
    }

    public static function number(int $value): string
    {
        if ($value < 0) {
            return 'moins '.self::number(-$value);
        }

        if ($value === 0) {
            return self::UNITS[0];
        }

        $parts = [];
        foreach ([1_000_000_000 => 'milliard', 1_000_000 => 'million'] as $scale => $name) {
            if ($value >= $scale) {
                $count = intdiv($value, $scale);
                $parts[] = self::belowThousand($count, false).' '.$name.($count > 1 ? 's' : '');
                $value %= $scale;
            }
        }

        if ($value >= 1000) {
            $thousands = intdiv($value, 1000);
            $parts[] = $thousands === 1 ? 'mille' : self::belowThousand($thousands, false).' mille';
            $value %= 1000;
        }

        if ($value > 0) {
            $parts[] = self::belowThousand($value, true);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $value, bool $isFinal): string
    {
        $hundreds = intdiv($value, 100);
        $rest = $value % 100;
        $parts = [];

        if ($hundreds > 0) {
            $hundredWord = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds].' cent';
            if ($hundreds > 1 && $rest === 0 && $isFinal) {
                $hundredWord .= 's';
            }
            $parts[] = $hundredWord;
        }

        if ($rest > 0) {
            $parts[] = self::belowHundred($rest, $isFinal);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $value, bool $isFinal): string
    {
        if ($value < 20) {
            return self::UNITS[$value];
        }

        $tens = intdiv($value, 10);
        $unit = $value % 10;

        if ($tens === 7 || $tens === 9) {
            $base = $tens === 7 ? 'soixante' : 'quatre-vingt';
            $separator = ($tens === 7 && $unit === 1) ? ' et ' : '-';

            return $base.$separator.self::UNITS[10 + $unit];
        }

        if ($tens === 8) {
            return $unit === 0 ? 'quatre-vingt'.($isFinal ? 's' : '') : 'quatre-vingt-'.self::UNITS[$unit];
        }

        $tensWord = match ($tens) {
            2 => 'vingt',
            3 => 'trente',
            4 => 'quarante',
            5 => 'cinquante',
            6 => 'soixante',
            default => throw new \LogicException('Dizaine hors plage.'),
        };

        if ($unit === 0) {
            return $tensWord;
        }

        return $tensWord.($unit === 1 ? ' et un' : '-'.self::UNITS[$unit]);
    }
}
