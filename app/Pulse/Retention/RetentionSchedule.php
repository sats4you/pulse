<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Retention;

use DateInterval;
use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Event\EventTiming;

final class RetentionSchedule
{
    public function attendanceDeleteAt(EventTiming $timing): DateTimeImmutable
    {
        return $timing->effectiveEnd()->add(new DateInterval('P7D'));
    }

    public function eventDeleteAt(EventTiming $timing): DateTimeImmutable
    {
        return $timing->effectiveEnd()->add(new DateInterval('P30D'));
    }
}
