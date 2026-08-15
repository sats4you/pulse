<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateInterval;
use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;

final readonly class AccessExchange
{
    public function __construct(
        private RoundAccessStore $store,
        private SecretDigester $digester,
        private AccessSessionCodec $sessionCodec,
    ) {
    }

    public function exchangeParticipant(
        string $publicSlug,
        string $rawSecret,
        DateTimeImmutable $now,
    ): ?string {
        if ($rawSecret === '') {
            return null;
        }

        $grant = $this->store->findParticipantGrant($publicSlug, $this->digester->digest($rawSecret));
        if ($grant === null) {
            return null;
        }

        return $this->sessionCodec->encode(new AccessSession(
            $grant->roundId,
            $grant->role,
            $grant->accessVersion,
            $now->add(new DateInterval('P180D')),
        ));
    }

    public function validateParticipant(string $cookie, string $publicSlug, DateTimeImmutable $now): ?AccessGrant
    {
        return $this->validate($cookie, $publicSlug, AccessRole::Participant, $now);
    }

    public function exchangeAdministrator(
        string $publicSlug,
        string $rawSecret,
        DateTimeImmutable $now,
    ): ?string {
        if ($rawSecret === '') {
            return null;
        }

        $grant = $this->store->findAdministratorGrant($publicSlug, $this->digester->digest($rawSecret));
        if ($grant === null) {
            return null;
        }

        return $this->sessionCodec->encode(new AccessSession(
            $grant->roundId,
            $grant->role,
            $grant->accessVersion,
            $now->add(new DateInterval('PT12H')),
        ));
    }

    public function validateAdministrator(string $cookie, string $publicSlug, DateTimeImmutable $now): ?AccessGrant
    {
        return $this->validate($cookie, $publicSlug, AccessRole::Administrator, $now);
    }

    private function validate(
        string $cookie,
        string $publicSlug,
        AccessRole $expectedRole,
        DateTimeImmutable $now,
    ): ?AccessGrant {
        $session = $this->sessionCodec->decode($cookie, $now);
        if ($session === null || $session->role !== $expectedRole) {
            return null;
        }

        $grant = new AccessGrant($session->roundId, $session->role, $session->accessVersion);

        return $this->store->isCurrent($grant, $publicSlug) ? $grant : null;
    }
}
