<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Support;

use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\RoundAccessStore;

final class InMemoryRoundAccessStore implements RoundAccessStore
{
    public string $slug = 'bern-bitcoin';
    public string $roundId = '0123456789abcdef0123456789abcdef';
    public string $participantDigest = '';
    public string $administratorDigest = '';
    public int $participantVersion = 1;
    public int $administratorVersion = 1;

    public function findParticipantGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        if ($publicSlug !== $this->slug || !hash_equals($this->participantDigest, $presentedDigest)) {
            return null;
        }

        return new AccessGrant($this->roundId, AccessRole::Participant, $this->participantVersion);
    }

    public function findAdministratorGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        if ($publicSlug !== $this->slug || !hash_equals($this->administratorDigest, $presentedDigest)) {
            return null;
        }

        return new AccessGrant($this->roundId, AccessRole::Administrator, $this->administratorVersion);
    }

    public function isCurrent(AccessGrant $grant, string $publicSlug): bool
    {
        return $publicSlug === $this->slug
            && $grant->roundId === $this->roundId
            && $grant->accessVersion === match ($grant->role) {
                AccessRole::Participant => $this->participantVersion,
                AccessRole::Administrator => $this->administratorVersion,
                AccessRole::Recovery => -1,
            };
    }
}
