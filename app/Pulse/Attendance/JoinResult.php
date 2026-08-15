<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;

final readonly class JoinResult
{
    public function __construct(
        public string $participantSecret,
        public int $count,
        public bool $alreadyJoined,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
