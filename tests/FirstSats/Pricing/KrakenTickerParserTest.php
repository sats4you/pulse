<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Pricing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sats4you\Pulse\FirstSats\Pricing\KrakenTickerParser;

final class KrakenTickerParserTest extends TestCase
{
    public function testOfficialTickerShapeReturnsBestBidAndAsk(): void
    {
        $json = '{"error":[],"result":{"XBTCHF":{"a":["90001.10000","1","1.000"],"b":["89999.90000","1","1.000"],"c":["90000","1"]}}}';

        self::assertSame(['bid' => '89999.90000', 'ask' => '90001.10000'], (new KrakenTickerParser())->parse($json));
    }

    #[DataProvider('invalidPayloads')]
    public function testMalformedOrAmbiguousResponseIsRejected(string $json): void
    {
        $this->expectException(RuntimeException::class);
        (new KrakenTickerParser())->parse($json);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'not json' => ['no'];
        yield 'api error' => ['{"error":["EGeneral:Unavailable"],"result":{}}'];
        yield 'no ticker' => ['{"error":[],"result":{}}'];
        yield 'multiple tickers' => ['{"error":[],"result":{"one":{},"two":{}}}'];
        yield 'missing bid' => ['{"error":[],"result":{"XBTCHF":{"a":["1"]}}}'];
    }
}
