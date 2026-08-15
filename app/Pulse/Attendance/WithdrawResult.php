<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

final readonly class WithdrawResult
{
    public function __construct(
        public int $count,
        public bool $withdrawn,
    ) {
    }
}
