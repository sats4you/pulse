<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;

interface AttendanceStore
{
    /** @template T @param callable(): T $operation @return T */
    public function transaction(callable $operation): mixed;

    public function getEventForUpdate(string $roundId, string $eventId): ?EventSnapshot;

    public function hasCommitment(string $eventId, string $secretDigest): bool;

    public function insertCommitment(
        string $eventId,
        string $secretDigest,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deleteAt,
    ): void;

    public function deleteCommitment(string $eventId, string $secretDigest): bool;

    public function countCommitments(string $eventId): int;
}
