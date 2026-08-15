<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

final readonly class AccessGrant
{
    public function __construct(
        public string $roundId,
        public AccessRole $role,
        public int $accessVersion,
    ) {
    }
}
