<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Attendance\AttendanceStore;
use Sats4you\Pulse\Pulse\Attendance\JoinResult;
use Sats4you\Pulse\Pulse\Attendance\WithdrawResult;

final readonly class ParticipantFlow
{
    public function __construct(
        private PublishedEventStore $eventStore,
        private AttendanceStore $attendanceStore,
        private AttendanceService $attendanceService,
        private SecretDigester $secretDigester,
    ) {
    }

    /** @return list<PublishedEvent> */
    public function events(AccessGrant $grant, DateTimeImmutable $now): array
    {
        return $this->eventStore->listUpcoming($grant->roundId, $now);
    }

    /** @param list<PublishedEvent> $events @param array<string, string> $rsvpSecrets */
    public function joinedByPublicId(array $events, array $rsvpSecrets): array
    {
        $joined = [];
        foreach ($events as $event) {
            $secret = $rsvpSecrets[$event->publicId] ?? null;
            $joined[$event->publicId] = $secret !== null
                && $secret !== ''
                && $this->attendanceStore->hasCommitment($event->id, $this->secretDigester->digest($secret));
        }

        return $joined;
    }

    public function join(
        AccessGrant $grant,
        string $publicEventId,
        ?string $existingSecret,
        DateTimeImmutable $now,
    ): JoinResult {
        $event = $this->eventStore->findUpcoming($grant->roundId, $publicEventId, $now);
        if ($event === null) {
            throw new \DomainException('event_unavailable');
        }

        return $this->attendanceService->join($grant->roundId, $event->id, $existingSecret, $now);
    }

    public function withdraw(
        AccessGrant $grant,
        string $publicEventId,
        string $secret,
        DateTimeImmutable $now,
    ): WithdrawResult {
        $event = $this->eventStore->findUpcoming($grant->roundId, $publicEventId, $now);
        if ($event === null) {
            throw new \DomainException('event_unavailable');
        }

        return $this->attendanceService->withdraw($grant->roundId, $event->id, $secret, $now);
    }
}
