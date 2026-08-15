<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;

final readonly class AccessSession
{
    public function __construct(
        public string $roundId,
        public AccessRole $role,
        public int $accessVersion,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
