<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

final readonly class InvitationCredentials
{
    public function __construct(
        public Invitation $invitation,
        public string $invitationSecret,
        public string $managementSecret,
        public string $recoveryCode,
    ) {
    }
}
