<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

use InvalidArgumentException;

/**
 * Immutable value object for monetary amounts.
 *
 * IMPORTANT — N-Genius always expects amounts in MINOR UNITS as an integer.
 * 100 AED = 10000 minor units (fils). Pass human-readable amounts through the
 * named constructors (::aed, ::of) which handle conversion and validation.
 *
 * Never pass raw integers or floats across service boundaries — always use Money.
 */
final readonly class Money
{
    /** ISO 4217 minor-unit multipliers for supported currencies. */
    private const MINOR_UNIT_MULTIPLIERS = [
        'AED' => 100,
        'USD' => 100,
        'EUR' => 100,
        'GBP' => 100,
        'SAR' => 100,
        'QAR' => 100,
        'KWD' => 1000,
        'BHD' => 1000,
        'OMR' => 1000,
        'JOD' => 1000,
    ];

    private function __construct(
        /** Amount in minor units (e.g. fils for AED). */
        public readonly int $minorUnits,
        /** ISO 4217 currency code. */
        public readonly string $currency,
    ) {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException("Money amount must not be negative; got {$minorUnits}.");
        }

        $currency = strtoupper($currency);

        if (! array_key_exists($currency, self::MINOR_UNIT_MULTIPLIERS)) {
            throw new InvalidArgumentException("Unsupported currency '{$currency}'.");
        }
    }

    /** Create from a decimal (human-readable) amount, e.g. Money::of(250.00, 'AED'). */
    public static function of(float|int $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        $multiplier = self::MINOR_UNIT_MULTIPLIERS[$currency]
            ?? throw new InvalidArgumentException("Unsupported currency '{$currency}'.");

        $minorUnits = (int) round($amount * $multiplier);

        return new self($minorUnits, $currency);
    }

    /** Convenience constructor for AED. */
    public static function aed(float|int $amount): self
    {
        return self::of($amount, 'AED');
    }

    /** Create directly from minor units (use when receiving values from the N-Genius API). */
    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /** Return the decimal representation (for display). */
    public function toDecimal(): float
    {
        $multiplier = self::MINOR_UNIT_MULTIPLIERS[$this->currency];

        return $this->minorUnits / $multiplier;
    }

    /** @throws InvalidArgumentException when currencies differ */
    public function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return number_format($this->toDecimal(), 2).' '.$this->currency;
    }
}
