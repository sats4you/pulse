<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Wallet;

use DateTimeImmutable;
use Sats4you\Pulse\FirstSats\Invitation\Invitation;
use Sats4you\Pulse\FirstSats\Invitation\InvitationStore;

final readonly class WalletJourney
{
    public function __construct(private InvitationStore $store)
    {
    }

    public function select(Invitation $invitation, string $walletPath, DateTimeImmutable $selectedAt): bool
    {
        if ($invitation->status !== 'accepted' || $invitation->satsAmount === 0
            || $invitation->stageFourCompletedAt === null
            || !in_array($walletPath, ['wos', 'phoenix', 'other'], true)) return false;

        return $this->store->selectWalletPath(
            $invitation->publicId,
            $invitation->invitationAccessVersion,
            $walletPath,
            $selectedAt,
        ) !== null;
    }
}
