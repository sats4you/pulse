<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

use DateTimeImmutable;

final readonly class NotificationBatch
{
    /** @param list<string> $changeIds */
    public function __construct(
        public string $eventId,
        public string $eventTitle,
        public DateTimeImmutable $startsAt,
        public int $joins,
        public int $withdrawals,
        public int $currentCount,
        public array $changeIds,
    ) {
    }
}
