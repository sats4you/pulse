<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Support;

use Sats4you\Pulse\Pulse\Administration\AdminEvent;
use Sats4you\Pulse\Pulse\Administration\AdminEventStore;

final class InMemoryAdminEventStore implements AdminEventStore
{
    /** @var array<string, AdminEvent> */
    public array $events = [];

    public function listAll(string $roundId): array
    {
        $events = array_values(array_filter(
            $this->events,
            static fn (AdminEvent $event): bool => $event->roundId === $roundId,
        ));
        usort($events, static fn (AdminEvent $left, AdminEvent $right): int => $right->details->timing->startsAt <=> $left->details->timing->startsAt);

        return $events;
    }

    public function find(string $roundId, string $publicEventId): ?AdminEvent
    {
        $event = $this->events[$publicEventId] ?? null;

        return $event?->roundId === $roundId ? $event : null;
    }

    public function insert(AdminEvent $event): void
    {
        if (isset($this->events[$event->publicId])) {
            throw new \UnexpectedValueException('Duplicate event.');
        }
        $this->events[$event->publicId] = $event;
    }

    public function save(AdminEvent $event): void
    {
        if (!isset($this->events[$event->publicId])) {
            throw new \UnexpectedValueException('Unknown event.');
        }
        $this->events[$event->publicId] = $event;
    }

    public function delete(string $roundId, string $publicEventId): bool
    {
        $event = $this->events[$publicEventId] ?? null;
        if ($event === null || $event->roundId !== $roundId) {
            return false;
        }
        unset($this->events[$publicEventId]);

        return true;
    }
}
