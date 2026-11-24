<?php

use App\Support\AmountInWords;

it('spells numbers in French', function (int $number, string $expected) {
    expect(AmountInWords::number($number))->toBe($expected);
})->with([
    [0, 'zéro'],
    [1, 'un'],
    [21, 'vingt et un'],
    [71, 'soixante et onze'],
    [80, 'quatre-vingts'],
    [81, 'quatre-vingt-un'],
    [99, 'quatre-vingt-dix-neuf'],
    [200, 'deux cents'],
    [201, 'deux cent un'],
    [1000, 'mille'],
    [1980, 'mille neuf cent quatre-vingts'],
    [80000, 'quatre-vingt mille'],
    [2500000, 'deux millions cinq cent mille'],
]);

it('spells TND amounts with dinars and millimes', function (string $amount, string $expected) {
    expect(AmountInWords::dinars($amount))->toBe($expected);
})->with([
    ['1250.500', 'mille deux cent cinquante dinars et cinq cents millimes'],
    ['1.000', 'un dinar'],
    ['0.001', 'zéro dinar et un millime'],
    ['119.000', 'cent dix-neuf dinars'],
]);
