<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;

interface RoundProvisioningStore
{
    public function createRound(
        string $roundId,
        string $publicSlug,
        string $name,
        string $timezone,
        string $participantDigest,
        string $administratorDigest,
        string $recoveryDigest,
        DateTimeImmutable $createdAt,
    ): void;
}
