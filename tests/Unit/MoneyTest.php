<?php

declare(strict_types=1);

use mbarky\Ngenius\DTO\Money;

// ------------------------------------------------------------------
// Construction & conversion
// ------------------------------------------------------------------

it('converts a decimal AED amount to minor units correctly', function () {
    $money = Money::aed(250.00);

    expect($money->minorUnits)->toBe(25000)
        ->and($money->currency)->toBe('AED');
});

it('converts back to decimal correctly', function () {
    $money = Money::aed(100.00);

    expect($money->toDecimal())->toBe(100.0);
});

it('round-trips minor units through toDecimal()', function () {
    $money = Money::fromMinorUnits(25000, 'AED');

    expect($money->toDecimal())->toBe(250.0)
        ->and($money->minorUnits)->toBe(25000);
});

it('constructs via Money::of() with explicit currency', function () {
    $money = Money::of(1.00, 'USD');

    expect($money->minorUnits)->toBe(100)
        ->and($money->currency)->toBe('USD');
});

it('normalises currency to uppercase', function () {
    $money = Money::of(10, 'aed');

    expect($money->currency)->toBe('AED');
});

it('handles KWD which has 3 decimal places (1000 minor units per 1 KWD)', function () {
    $money = Money::of(1.000, 'KWD');

    expect($money->minorUnits)->toBe(1000);
});

it('handles a fractional AED amount that rounds correctly', function () {
    // 0.01 AED = 1 fil (minor unit)
    $money = Money::aed(0.01);
    expect($money->minorUnits)->toBe(1);

    // 0.005 AED rounds to 1 fil (round half up)
    $money2 = Money::aed(0.005);
    expect($money2->minorUnits)->toBe(1);
});

it('produces a human-readable __toString()', function () {
    $money = Money::aed(250.00);

    expect((string) $money)->toBe('250.00 AED');
});

// ------------------------------------------------------------------
// equals()
// ------------------------------------------------------------------

it('considers two equal Money objects as equal', function () {
    $a = Money::aed(100.00);
    $b = Money::fromMinorUnits(10000, 'AED');

    expect($a->equals($b))->toBeTrue();
});

it('considers Money objects with different amounts as not equal', function () {
    $a = Money::aed(100.00);
    $b = Money::aed(200.00);

    expect($a->equals($b))->toBeFalse();
});

it('considers Money objects with different currencies as not equal', function () {
    $a = Money::of(100.00, 'AED');
    $b = Money::of(100.00, 'USD');

    expect($a->equals($b))->toBeFalse();
});

// ------------------------------------------------------------------
// assertSameCurrency()
// ------------------------------------------------------------------

it('assertSameCurrency() does not throw when currencies match', function () {
    $a = Money::aed(50.00);
    $b = Money::aed(100.00);

    expect(fn () => $a->assertSameCurrency($b))->not->toThrow(InvalidArgumentException::class);
});

it('assertSameCurrency() throws InvalidArgumentException on mismatch', function () {
    $aed = Money::aed(50.00);
    $usd = Money::of(50.00, 'USD');

    expect(fn () => $aed->assertSameCurrency($usd))
        ->toThrow(InvalidArgumentException::class, 'Currency mismatch');
});

// ------------------------------------------------------------------
// Validation guards
// ------------------------------------------------------------------

it('throws InvalidArgumentException for a negative amount', function () {
    expect(fn () => Money::fromMinorUnits(-1, 'AED'))
        ->toThrow(InvalidArgumentException::class, 'negative');
});

it('throws InvalidArgumentException for an unsupported currency', function () {
    expect(fn () => Money::of(100, 'XYZ'))
        ->toThrow(InvalidArgumentException::class, "Unsupported currency 'XYZ'");
});

it('allows a zero amount', function () {
    $money = Money::aed(0);

    expect($money->minorUnits)->toBe(0);
});
