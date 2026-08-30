<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

final readonly class InvitationInput
{
    public function __construct(
        public string $locale,
        public string $inviterLabel,
        public string $inviteeLabel,
        public ?string $personalMessage,
        public int $satsAmount,
        public bool $adultAttested,
        public bool $termsAccepted,
        public string $termsVersion,
        public bool $highAmountAcknowledged = false,
    ) {
    }
}
