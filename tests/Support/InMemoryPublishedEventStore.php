<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Support;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\PublicAccess\PublishedEvent;
use Sats4you\Pulse\Pulse\PublicAccess\PublishedEventStore;

final class InMemoryPublishedEventStore implements PublishedEventStore
{
    /** @var array<string, PublishedEvent> */
    public array $events = [];

    public function listUpcoming(string $roundId, DateTimeImmutable $now): array
    {
        return array_values($this->events);
    }

    public function findUpcoming(string $roundId, string $publicEventId, DateTimeImmutable $now): ?PublishedEvent
    {
        $event = $this->events[$publicEventId] ?? null;

        return $event;
    }
}
