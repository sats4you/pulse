<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Support;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Attendance\AttendanceStore;
use Sats4you\Pulse\Pulse\Attendance\EventSnapshot;

final class InMemoryAttendanceStore implements AttendanceStore
{
    /** @var array<string, EventSnapshot> */
    public array $events = [];

    /** @var array<string, array<string, array{createdAt: DateTimeImmutable, deleteAt: DateTimeImmutable}>> */
    public array $commitments = [];

    public int $transactionCount = 0;

    /** @var list<array{eventId: string, changeType: string, occurredAt: DateTimeImmutable}> */
    public array $notificationChanges = [];

    public function transaction(callable $operation): mixed
    {
        ++$this->transactionCount;

        return $operation();
    }

    public function getEventForUpdate(string $roundId, string $eventId): ?EventSnapshot
    {
        $event = $this->events[$eventId] ?? null;

        return $event?->roundId === $roundId ? $event : null;
    }

    public function addCommitment(
        string $eventId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deleteAt,
    ): void {
        $this->commitments[$eventId][bin2hex(random_bytes(32))] = compact('createdAt', 'deleteAt');
    }

    public function removeAnyCommitment(string $eventId): bool
    {
        $key = array_key_first($this->commitments[$eventId] ?? []);
        if ($key === null) {
            return false;
        }

        unset($this->commitments[$eventId][$key]);

        return true;
    }

    public function countCommitments(string $eventId): int
    {
        return count($this->commitments[$eventId] ?? []);
    }

    public function recordNotificationChange(
        string $eventId,
        string $changeType,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->notificationChanges[] = compact('eventId', 'changeType', 'occurredAt');
    }
}
