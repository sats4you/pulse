<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Pricing;

use DateTimeImmutable;

final class UnavailableChfQuoteProvider implements ChfQuoteProvider
{
    public function current(DateTimeImmutable $now): ?ChfQuote
    {
        return null;
    }
}
