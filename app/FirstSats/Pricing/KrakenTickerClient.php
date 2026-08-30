<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

interface KrakenTickerClient
{
    /** @return array{bid:string,ask:string} */
    public function fetch(): array;
}
