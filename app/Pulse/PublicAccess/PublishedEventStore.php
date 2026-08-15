<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;

interface PublishedEventStore
{
    /** @return list<PublishedEvent> */
    public function listUpcoming(string $roundId, DateTimeImmutable $now): array;

    public function findUpcoming(string $roundId, string $publicEventId, DateTimeImmutable $now): ?PublishedEvent;
}
