<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PdoCachedChfQuoteProvider implements ChfQuoteProvider
{
    private ?ChfQuote $memory = null;

    public function __construct(private readonly PDO $db, private readonly KrakenTickerClient $kraken)
    {
    }

    public function current(DateTimeImmutable $now): ?ChfQuote
    {
        $cached = $this->memory ?? $this->load();
        if ($cached !== null && $cached->fetchedAt >= $now->sub(new DateInterval('PT5M'))) {
            return $this->memory = $cached;
        }

        try {
            $ticker = $this->kraken->fetch();
            $fresh = ChfQuote::fromDecimalStrings($ticker['bid'], $ticker['ask'], $now);
            $this->save($fresh);

            return $this->memory = $fresh;
        } catch (\Throwable) {
            if ($cached !== null && $cached->fetchedAt >= $now->sub(new DateInterval('PT15M'))) {
                return $this->memory = $cached;
            }

            return null;
        }
    }

    private function load(): ?ChfQuote
    {
        $row = $this->db->query("SELECT bid_units,ask_units,fetched_at FROM first_sats_chf_quote_cache WHERE cache_key='kraken-xbt-chf'")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        try {
            return new ChfQuote((int) $row['bid_units'], (int) $row['ask_units'], new DateTimeImmutable((string) $row['fetched_at'], new DateTimeZone('UTC')));
        } catch (\Throwable) {
            return null;
        }
    }

    private function save(ChfQuote $quote): void
    {
        $params = ['key' => 'kraken-xbt-chf', 'bid' => $quote->bidUnits, 'ask' => $quote->askUnits, 'at' => $quote->fetchedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')];
        $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO first_sats_chf_quote_cache (cache_key,bid_units,ask_units,fetched_at) VALUES (:key,:bid,:ask,:at) ON CONFLICT(cache_key) DO UPDATE SET bid_units=excluded.bid_units,ask_units=excluded.ask_units,fetched_at=excluded.fetched_at'
            : 'INSERT INTO first_sats_chf_quote_cache (cache_key,bid_units,ask_units,fetched_at) VALUES (:key,:bid,:ask,:at) ON DUPLICATE KEY UPDATE bid_units=VALUES(bid_units),ask_units=VALUES(ask_units),fetched_at=VALUES(fetched_at)';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
    }
}
