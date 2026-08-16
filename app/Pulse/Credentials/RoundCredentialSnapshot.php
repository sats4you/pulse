<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

final readonly class RoundCredentialSnapshot
{
    public function __construct(
        public string $roundId,
        public string $publicSlug,
        public int $participantVersion,
        public int $administratorVersion,
        public int $recoveryVersion,
    ) {
    }
}
