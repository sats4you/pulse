<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Retention;

final readonly class RetentionReport
{
    public function __construct(
        public int $attendanceDeleted,
        public int $eventsDeleted,
    ) {
    }
}
