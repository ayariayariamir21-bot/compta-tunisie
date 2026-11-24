<?php

use App\Support\Decimal;

it('normalizes valid decimal strings to three decimals', function (string|int|null $input, string $expected) {
    expect(Decimal::normalize($input))->toBe($expected);
})->with([
    ['12', '12.000'],
    ['12.5', '12.500'],
    ['-0.25', '-0.250'],
    [7, '7.000'],
    [null, '0.000'],
]);

it('rejects values with more than three decimals or invalid input', function (string $input) {
    Decimal::normalize($input);
})->throws(InvalidArgumentException::class)->with(['1.2345', 'abc', '1,5', '']);

it('rounds database and float values half away from zero', function (int|float|string|null $input, string $expected) {
    expect(Decimal::round($input))->toBe($expected);
})->with([
    [0.1 + 0.2, '0.300'],
    ['1234.5675', '1234.568'],
    ['-1.0005', '-1.001'],
    ['1,250.5', '1250.500'],
    [19, '19.000'],
    [null, '0.000'],
    ['', '0.000'],
]);

it('rejects non numeric values when rounding', function () {
    Decimal::round('1e3');
})->throws(InvalidArgumentException::class);
