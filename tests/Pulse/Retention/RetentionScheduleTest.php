<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Retention;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;

final class RetentionScheduleTest extends TestCase
{
    public function testDeadlinesUseEffectiveEventEnd(): void
    {
        $timing = new EventTiming(new DateTimeImmutable('2026-08-27T16:30:00Z'), null);
        $schedule = new RetentionSchedule();

        self::assertSame('2026-09-03T22:30:00+00:00', $schedule->attendanceDeleteAt($timing)->format(DATE_ATOM));
        self::assertSame('2026-09-26T22:30:00+00:00', $schedule->eventDeleteAt($timing)->format(DATE_ATOM));
    }
}
