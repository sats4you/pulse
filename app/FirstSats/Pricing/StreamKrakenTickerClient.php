<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

use RuntimeException;

final readonly class StreamKrakenTickerClient implements KrakenTickerClient
{
    private const URL = 'https://api.kraken.com/0/public/Ticker?pair=XBTCHF';

    public function __construct(private KrakenTickerParser $parser)
    {
    }

    public function fetch(): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => false,
            'header' => "Accept: application/json\r\nUser-Agent: sats4you-first-sats/1.0\r\n",
        ]]);
        $body = @file_get_contents(self::URL, false, $context, 0, 65_536);
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('Kraken ticker is unavailable.');
        }

        return $this->parser->parse($body);
    }
}
