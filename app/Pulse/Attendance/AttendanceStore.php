<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;

interface AttendanceStore
{
    /** @template T @param callable(): T $operation @return T */
    public function transaction(callable $operation): mixed;

    public function getEventForUpdate(string $roundId, string $eventId): ?EventSnapshot;

    public function addCommitment(
        string $eventId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deleteAt,
    ): void;

    public function removeAnyCommitment(string $eventId): bool;

    public function countCommitments(string $eventId): int;

    public function recordNotificationChange(
        string $eventId,
        string $changeType,
        DateTimeImmutable $occurredAt,
    ): void;
}
