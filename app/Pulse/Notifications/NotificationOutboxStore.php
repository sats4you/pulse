<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

use DateTimeImmutable;

interface NotificationOutboxStore
{
    public function acquireDispatchLock(): bool;

    public function releaseDispatchLock(): void;

    public function deleteExpired(DateTimeImmutable $now): int;

    /** @return list<NotificationBatch> */
    public function pendingBatches(DateTimeImmutable $readyBefore): array;

    /** @param list<string> $changeIds */
    public function deleteChanges(array $changeIds): void;
}
