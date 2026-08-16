<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

interface AdminEventStore
{
    /** @return list<AdminEvent> */
    public function listAll(string $roundId): array;

    public function find(string $roundId, string $publicEventId): ?AdminEvent;

    public function insert(AdminEvent $event): void;

    public function save(AdminEvent $event): void;

    public function delete(string $roundId, string $publicEventId): bool;
}
