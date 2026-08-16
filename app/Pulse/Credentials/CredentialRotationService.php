<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;
use DomainException;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;

final readonly class CredentialRotationService
{
    public function __construct(
        private CredentialStore $store,
        private SecretGenerator $generator,
        private SecretDigester $digester,
    ) {
    }

    public function recoverAdministrator(
        AccessGrant $recoveryGrant,
        string $publicSlug,
        DateTimeImmutable $now,
    ): AdministratorRecoveryResult {
        if ($recoveryGrant->role !== AccessRole::Recovery) {
            throw new DomainException('recovery_grant_required');
        }

        return $this->store->transaction(function () use ($recoveryGrant, $publicSlug, $now): AdministratorRecoveryResult {
            $round = $this->currentRound($recoveryGrant, $publicSlug, AccessRole::Recovery);
            $administratorSecret = $this->generator->generate();
            $recoverySecret = $this->generator->generate();
            if (!$this->store->replaceAdministratorCredentials(
                $round,
                $this->digester->digest($administratorSecret),
                $this->digester->digest($recoverySecret),
                $now,
            )) {
                throw new DomainException('credential_rotation_conflict');
            }

            return new AdministratorRecoveryResult($administratorSecret, $recoverySecret);
        });
    }

    public function rotateParticipant(
        AccessGrant $administratorGrant,
        string $publicSlug,
        DateTimeImmutable $now,
    ): ParticipantRotationResult {
        if ($administratorGrant->role !== AccessRole::Administrator) {
            throw new DomainException('administrator_grant_required');
        }

        return $this->store->transaction(function () use ($administratorGrant, $publicSlug, $now): ParticipantRotationResult {
            $round = $this->currentRound($administratorGrant, $publicSlug, AccessRole::Administrator);
            $participantSecret = $this->generator->generate();
            if (!$this->store->replaceParticipantCredential(
                $round,
                $this->digester->digest($participantSecret),
                $now,
            )) {
                throw new DomainException('credential_rotation_conflict');
            }

            return new ParticipantRotationResult($participantSecret);
        });
    }

    private function currentRound(
        AccessGrant $grant,
        string $publicSlug,
        AccessRole $role,
    ): RoundCredentialSnapshot {
        $round = $this->store->getRoundForUpdate($publicSlug);
        $version = match ($role) {
            AccessRole::Participant => $round?->participantVersion,
            AccessRole::Administrator => $round?->administratorVersion,
            AccessRole::Recovery => $round?->recoveryVersion,
        };
        if ($round === null
            || !hash_equals($round->roundId, $grant->roundId)
            || $version !== $grant->accessVersion
        ) {
            throw new DomainException('credential_grant_stale');
        }

        return $round;
    }
}
