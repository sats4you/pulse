<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

use DateTimeImmutable;
use DomainException;

final readonly class ChfQuote
{
    private const PRICE_SCALE = 10_000;
    private const SATS_PER_BTC = 100_000_000;
    private const MAX_PRICE_UNITS = 100_000_000_000; // CHF 10 million per BTC.

    public function __construct(
        public int $bidUnits,
        public int $askUnits,
        public DateTimeImmutable $fetchedAt,
    ) {
        if ($bidUnits <= 0 || $askUnits <= 0 || $bidUnits > self::MAX_PRICE_UNITS || $askUnits > self::MAX_PRICE_UNITS) {
            throw new DomainException('Invalid CHF quote.');
        }
        if ($askUnits < $bidUnits) {
            throw new DomainException('Crossed CHF quote.');
        }
        // spread / midpoint <= 2%  <=>  (ask - bid) * 100 <= ask + bid
        if (($askUnits - $bidUnits) * 100 > $askUnits + $bidUnits) {
            throw new DomainException('CHF quote spread is too wide.');
        }
    }

    public static function fromDecimalStrings(string $bid, string $ask, DateTimeImmutable $fetchedAt): self
    {
        return new self(self::decimalUnits($bid), self::decimalUnits($ask), $fetchedAt);
    }

    public function estimateCents(int $sats): int
    {
        if ($sats < 0 || $sats > 100_000) {
            throw new DomainException('Unsupported Sats amount.');
        }
        if ($sats === 0) {
            return 0;
        }

        $denominator = 2 * self::SATS_PER_BTC * (self::PRICE_SCALE / 100);
        $numerator = $sats * ($this->bidUnits + $this->askUnits);

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    public function midpointCents(): int
    {
        return intdiv($this->bidUnits + $this->askUnits + 100, 200);
    }

    public function sumUnits(): int
    {
        return $this->bidUnits + $this->askUnits;
    }

    private static function decimalUnits(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^([0-9]{1,8})(?:\.([0-9]+))?$/', $value, $matches) !== 1) {
            throw new DomainException('Invalid decimal CHF quote.');
        }

        $fraction = str_pad($matches[2] ?? '', 5, '0');
        $units = ((int) $matches[1] * self::PRICE_SCALE) + (int) substr($fraction, 0, 4);
        if ((int) $fraction[4] >= 5) {
            ++$units;
        }

        return $units;
    }
}
