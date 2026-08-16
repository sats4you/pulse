<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

final readonly class InitialCredentialSet
{
    public function __construct(
        public string $participantSecret,
        public string $administratorSecret,
        public string $recoverySecret,
    ) {
    }
}
