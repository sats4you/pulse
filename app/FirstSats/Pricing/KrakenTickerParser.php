<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

use RuntimeException;

final class KrakenTickerParser
{
    /** @return array{bid:string,ask:string} */
    public function parse(string $json): array
    {
        try {
            $payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Invalid Kraken response.');
        }
        if (!is_array($payload) || ($payload['error'] ?? null) !== [] || !is_array($payload['result'] ?? null) || count($payload['result']) !== 1) {
            throw new RuntimeException('Invalid Kraken response.');
        }
        $ticker = reset($payload['result']);
        $ask = is_array($ticker) && is_array($ticker['a'] ?? null) ? ($ticker['a'][0] ?? null) : null;
        $bid = is_array($ticker) && is_array($ticker['b'] ?? null) ? ($ticker['b'][0] ?? null) : null;
        if (!is_string($ask) || !is_string($bid)) {
            throw new RuntimeException('Invalid Kraken response.');
        }

        return ['bid' => $bid, 'ask' => $ask];
    }
}
