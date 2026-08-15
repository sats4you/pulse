<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

interface RoundAccessStore
{
    public function findParticipantGrant(string $publicSlug, string $presentedDigest): ?AccessGrant;

    public function findAdministratorGrant(string $publicSlug, string $presentedDigest): ?AccessGrant;

    public function isCurrent(AccessGrant $grant, string $publicSlug): bool;
}
