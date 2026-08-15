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

    public function hasCommitment(string $eventId, string $secretDigest): bool
    {
        return isset($this->commitments[$eventId][bin2hex($secretDigest)]);
    }

    public function insertCommitment(
        string $eventId,
        string $secretDigest,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deleteAt,
    ): void {
        $this->commitments[$eventId][bin2hex($secretDigest)] = compact('createdAt', 'deleteAt');
    }

    public function deleteCommitment(string $eventId, string $secretDigest): bool
    {
        $key = bin2hex($secretDigest);
        if (!isset($this->commitments[$eventId][$key])) {
            return false;
        }

        unset($this->commitments[$eventId][$key]);

        return true;
    }

    public function countCommitments(string $eventId): int
    {
        return count($this->commitments[$eventId] ?? []);
    }
}
