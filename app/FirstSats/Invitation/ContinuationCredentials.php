<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

final readonly class ContinuationCredentials
{
    public function __construct(
        public Invitation $invitation,
        public string $secret,
    ) {
    }
}
