<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Event;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class EventTiming
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
    ) {
        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Event end must be later than event start.');
        }
    }

    public function effectiveEnd(): DateTimeImmutable
    {
        return $this->endsAt ?? $this->startsAt->add(new DateInterval('PT6H'));
    }
}
