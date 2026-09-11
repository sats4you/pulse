<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

final readonly class JoinResult
{
    public function __construct(
        public int $count,
    ) {
    }
}
