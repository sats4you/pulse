<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final readonly class ParticipantJourney
{
    public function __construct(
        private InvitationStore $store,
        private SecretGenerator $secrets,
        private SecretDigester $digester,
    ) {
    }

    public function accept(Invitation $invitation, bool $adultAttested, bool $termsAccepted, string $termsVersion, DateTimeImmutable $now): ?Invitation
    {
        if (!$adultAttested) {
            throw new DomainException('adult_attestation_required');
        }
        if (!$termsAccepted || $termsVersion === '') {
            throw new DomainException('terms_required');
        }
        if ($invitation->status !== 'open' || $invitation->acceptUntil < $now) {
            return null;
        }

        $discardedSecret = $this->secrets->generate();
        return $this->store->accept(
            $invitation->publicId,
            $invitation->invitationAccessVersion,
            $this->digester->digest($discardedSecret),
            $termsVersion,
            $now,
            $now->add(new DateInterval('P30D')),
        );
    }

    public function createContinuation(Invitation $invitation, DateTimeImmutable $now): ?ContinuationCredentials
    {
        if ($invitation->status !== 'accepted') {
            return null;
        }
        $secret = $this->secrets->generate();
        $updated = $this->store->rotateInvitationAccess(
            $invitation->publicId,
            $invitation->invitationAccessVersion,
            $this->digester->digest($secret),
            $now,
        );

        return $updated === null ? null : new ContinuationCredentials($updated, $secret);
    }

    public function end(Invitation $invitation,DateTimeImmutable $now):?Invitation
    {
        if($invitation->status!=='accepted'||$invitation->stageFourCompletedAt!==null)return null;
        return $this->store->endParticipation(
            $invitation->publicId,$invitation->invitationAccessVersion,
            $this->digester->digest($this->secrets->generate()),$now,$now->add(new DateInterval('P30D')),
        );
    }
}
