<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Operations;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Operations\PdoOperationalHeartbeatStore;

final class PdoOperationalHeartbeatStoreTest extends TestCase
{
    public function testRecordsAndUpdatesOnlyTheAggregateSuccessTime(): void
    {
        $connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('CREATE TABLE operational_heartbeats (operation_key TEXT PRIMARY KEY, last_succeeded_at TEXT NOT NULL)');
        $store = new PdoOperationalHeartbeatStore($connection);

        self::assertNull($store->lastSuccess('retention'));
        $store->recordSuccess('retention', new DateTimeImmutable('2026-08-30T08:00:00+00:00'));
        self::assertSame('2026-08-30 08:00:00.000000', $store->lastSuccess('retention')?->format('Y-m-d H:i:s.u'));
        $store->recordSuccess('retention', new DateTimeImmutable('2026-08-30T09:15:00+00:00'));
        self::assertSame('2026-08-30 09:15:00.000000', $store->lastSuccess('retention')?->format('Y-m-d H:i:s.u'));
        self::assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM operational_heartbeats')->fetchColumn());
    }

    public function testRejectsAnUnboundedOperationKey(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE operational_heartbeats (operation_key TEXT PRIMARY KEY, last_succeeded_at TEXT NOT NULL)');
        $store = new PdoOperationalHeartbeatStore($connection);

        $this->expectException(InvalidArgumentException::class);
        $store->recordSuccess('../secret', new DateTimeImmutable());
    }
}
