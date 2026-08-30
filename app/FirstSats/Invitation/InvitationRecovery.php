<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final readonly class InvitationRecovery
{
    public function __construct(
        private InvitationStore $store,
        private SecretGenerator $secrets,
        private SecretDigester $digester,
    ) {
    }

    public function recover(string $code, DateTimeImmutable $now): ?RecoveryCredentials
    {
        $code = trim($code);
        if (preg_match('/^([a-f0-9]{24})\.([A-Za-z0-9_-]{43})$/', $code, $parts) !== 1) {
            return null;
        }
        $invitation = $this->store->findByPublicId($parts[1]);
        if ($invitation === null || !$this->digester->matches($code, $invitation->recoveryDigest)) {
            return null;
        }

        $managementSecret = $this->secrets->generate();
        $recoveryCode = $invitation->publicId . '.' . $this->secrets->generate();
        $rotated = $this->store->rotateManagementRecovery(
            $invitation->publicId,
            $invitation->managementAccessVersion,
            $invitation->recoveryVersion,
            $this->digester->digest($managementSecret),
            $this->digester->digest($recoveryCode),
            $now,
        );

        return $rotated === null ? null : new RecoveryCredentials($rotated, $managementSecret, $recoveryCode);
    }
}
