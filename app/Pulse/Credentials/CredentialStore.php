<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;

interface CredentialStore
{
    public function transaction(callable $operation): mixed;

    public function getRoundForUpdate(string $publicSlug): ?RoundCredentialSnapshot;

    public function replaceAdministratorCredentials(
        RoundCredentialSnapshot $expected,
        string $administratorDigest,
        string $recoveryDigest,
        DateTimeImmutable $rotatedAt,
    ): bool;

    public function replaceParticipantCredential(
        RoundCredentialSnapshot $expected,
        string $participantDigest,
        DateTimeImmutable $rotatedAt,
    ): bool;
}
