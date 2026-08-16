<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

final readonly class AdministratorRecoveryResult
{
    public function __construct(
        public string $administratorSecret,
        public string $recoverySecret,
    ) {
    }
}
