<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Retention;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Retention\RetentionService;
use Sats4you\Pulse\Pulse\Retention\RetentionStore;

final class RetentionServiceTest extends TestCase
{
    public function testDeletionRunIsRepeatableAndReportsOnlyCounts(): void
    {
        $store = new class implements RetentionStore {
            public int $attendance = 3;
            public int $events = 1;

            public function deleteDueAttendance(DateTimeImmutable $now): int
            {
                $deleted = $this->attendance;
                $this->attendance = 0;

                return $deleted;
            }

            public function deleteDueEvents(DateTimeImmutable $now): int
            {
                $deleted = $this->events;
                $this->events = 0;

                return $deleted;
            }
        };
        $service = new RetentionService($store);

        $first = $service->run(new DateTimeImmutable('2026-09-30T00:00:00Z'));
        self::assertSame(3, $first->attendanceDeleted);
        self::assertSame(1, $first->eventsDeleted);

        $second = $service->run(new DateTimeImmutable('2026-09-30T00:00:00Z'));
        self::assertSame(0, $second->attendanceDeleted);
        self::assertSame(0, $second->eventsDeleted);
    }
}
