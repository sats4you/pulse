<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Pricing;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sats4you\Pulse\FirstSats\Pricing\KrakenTickerClient;
use Sats4you\Pulse\FirstSats\Pricing\PdoCachedChfQuoteProvider;

final class PdoCachedChfQuoteProviderTest extends TestCase
{
    public function testFreshQuoteIsPersistedAndReusedForFiveMinutes(): void
    {
        $db = $this->database();
        $client = new FakeKrakenTickerClient(['bid' => '89999', 'ask' => '90001']);
        $provider = new PdoCachedChfQuoteProvider($db, $client);
        $now = new DateTimeImmutable('2026-08-24 12:00:00');

        self::assertSame(450, $provider->current($now)?->estimateCents(5_000));
        self::assertSame(450, $provider->current($now->modify('+4 minutes'))?->estimateCents(5_000));
        self::assertSame(1, $client->calls);
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM first_sats_chf_quote_cache')->fetchColumn());
    }

    public function testFailedRefreshUsesCacheForAtMostFifteenMinutes(): void
    {
        $db = $this->database();
        $db->exec("INSERT INTO first_sats_chf_quote_cache VALUES ('kraken-xbt-chf',899990000,900010000,'2026-08-24 12:00:00.000000')");
        $client = new FakeKrakenTickerClient(null);

        self::assertNotNull((new PdoCachedChfQuoteProvider($db, $client))->current(new DateTimeImmutable('2026-08-24 12:14:59')));
        self::assertNull((new PdoCachedChfQuoteProvider($db, $client))->current(new DateTimeImmutable('2026-08-24 12:15:01')));
    }

    public function testInvalidFreshQuoteDoesNotReplaceValidCachedQuote(): void
    {
        $db = $this->database();
        $db->exec("INSERT INTO first_sats_chf_quote_cache VALUES ('kraken-xbt-chf',899990000,900010000,'2026-08-24 12:00:00.000000')");
        $client = new FakeKrakenTickerClient(['bid' => '95000', 'ask' => '90000']);
        $quote = (new PdoCachedChfQuoteProvider($db, $client))->current(new DateTimeImmutable('2026-08-24 12:10:00'));

        self::assertSame(9_000_000, $quote?->midpointCents());
        self::assertSame('899990000', (string) $db->query("SELECT bid_units FROM first_sats_chf_quote_cache WHERE cache_key='kraken-xbt-chf'")->fetchColumn());
    }

    private function database(): PDO
    {
        $db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE first_sats_chf_quote_cache (cache_key TEXT PRIMARY KEY,bid_units INTEGER NOT NULL,ask_units INTEGER NOT NULL,fetched_at TEXT NOT NULL)');

        return $db;
    }
}

final class FakeKrakenTickerClient implements KrakenTickerClient
{
    public int $calls = 0;

    /** @param null|array{bid:string,ask:string} $result */
    public function __construct(private readonly ?array $result)
    {
    }

    public function fetch(): array
    {
        ++$this->calls;
        if ($this->result === null) {
            throw new RuntimeException('offline');
        }

        return $this->result;
    }
}
