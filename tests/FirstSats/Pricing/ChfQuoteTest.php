<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Pricing;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Pricing\ChfQuote;

final class ChfQuoteTest extends TestCase
{
    #[DataProvider('estimates')]
    public function testEstimateUsesBidAskMidpointAndCommercialRounding(int $sats, int $expectedCents): void
    {
        $quote = ChfQuote::fromDecimalStrings('89999.995', '90000.005', new DateTimeImmutable('2026-08-24 12:00:00'));

        self::assertSame($expectedCents, $quote->estimateCents($sats));
        self::assertSame(9_000_000, $quote->midpointCents());
    }

    /** @return iterable<string,array{int,int}> */
    public static function estimates(): iterable
    {
        yield 'zero' => [0, 0];
        yield 'one sat below one cent' => [1, 0];
        yield 'one thousand sats' => [1_000, 90];
        yield 'five thousand sats' => [5_000, 450];
        yield 'maximum' => [100_000, 9_000];
    }

    public function testCrossedQuoteIsRejected(): void
    {
        $this->expectException(DomainException::class);
        ChfQuote::fromDecimalStrings('90001', '90000', new DateTimeImmutable());
    }

    public function testSpreadAboveTwoPercentIsRejected(): void
    {
        $this->expectException(DomainException::class);
        ChfQuote::fromDecimalStrings('80000', '90000', new DateTimeImmutable());
    }
}
