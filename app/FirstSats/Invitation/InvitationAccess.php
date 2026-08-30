<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateInterval;
use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSession;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final readonly class InvitationAccess
{
    public function __construct(
        private InvitationStore $store,
        private SecretDigester $digester,
        private AccessSessionCodec $sessions,
    ) {
    }

    public function exchange(string $publicId, string $secret, AccessRole $role, DateTimeImmutable $now): ?string
    {
        $invitation = $this->store->findByPublicId($publicId);
        if ($invitation === null || $secret === '' || !in_array($role, [AccessRole::FirstSatsInvitation, AccessRole::FirstSatsManagement], true)) {
            return null;
        }
        if ($role === AccessRole::FirstSatsInvitation && !$this->participantWindowOpen($invitation, $now)) {
            return null;
        }
        [$digest, $version, $duration] = $role === AccessRole::FirstSatsInvitation
            ? [$invitation->invitationAccessDigest, $invitation->invitationAccessVersion, 'P30D']
            : [$invitation->managementAccessDigest, $invitation->managementAccessVersion, 'PT12H'];
        if (!$this->digester->matches($secret, $digest)) {
            return null;
        }

        return $this->session($invitation, $role, $now, $duration);
    }

    public function authorise(string $publicId, string $cookie, AccessRole $role, DateTimeImmutable $now): ?Invitation
    {
        $session = $this->sessions->decode($cookie, $now);
        $invitation = $this->store->findByPublicId($publicId);
        if ($session === null || $invitation === null || $session->role !== $role || $session->roundId !== $invitation->id) {
            return null;
        }
        if ($role === AccessRole::FirstSatsInvitation && !$this->participantWindowOpen($invitation, $now)) {
            return null;
        }
        $version = $role === AccessRole::FirstSatsInvitation
            ? $invitation->invitationAccessVersion
            : $invitation->managementAccessVersion;

        return $session->accessVersion === $version ? $invitation : null;
    }

    public function session(Invitation $invitation, AccessRole $role, DateTimeImmutable $now, string $duration = 'P30D'): string
    {
        if (!in_array($role, [AccessRole::FirstSatsInvitation, AccessRole::FirstSatsManagement], true)) {
            throw new \InvalidArgumentException('Unsupported access role.');
        }
        $version = $role === AccessRole::FirstSatsInvitation
            ? $invitation->invitationAccessVersion
            : $invitation->managementAccessVersion;

        return $this->sessions->encode(new AccessSession(
            $invitation->id,
            $role,
            $version,
            $now->add(new DateInterval($duration)),
        ));
    }

    private function participantWindowOpen(Invitation $i,DateTimeImmutable $now):bool
    {
        if($i->status==='open')return $i->acceptUntil >= $now;
        if($i->status!=='accepted')return false;
        return $i->stageFourCompletedAt!==null||($i->completeUntil!==null&&$i->completeUntil >= $now);
    }
}
