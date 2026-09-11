<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Attendance\JoinResult;
use Sats4you\Pulse\Pulse\Attendance\WithdrawResult;

final readonly class ParticipantFlow
{
    public function __construct(
        private PublishedEventStore $eventStore,
        private AttendanceService $attendanceService,
    ) {
    }

    /** @return list<PublishedEvent> */
    public function events(AccessGrant $grant, DateTimeImmutable $now): array
    {
        return $this->eventStore->listUpcoming($grant->roundId, $now);
    }

    public function join(
        AccessGrant $grant,
        string $publicEventId,
        DateTimeImmutable $now,
    ): JoinResult {
        $event = $this->eventStore->findUpcoming($grant->roundId, $publicEventId, $now);
        if ($event === null) {
            throw new \DomainException('event_unavailable');
        }

        return $this->attendanceService->join($grant->roundId, $event->id, $now);
    }

    public function withdraw(
        AccessGrant $grant,
        string $publicEventId,
        DateTimeImmutable $now,
    ): WithdrawResult {
        $event = $this->eventStore->findUpcoming($grant->roundId, $publicEventId, $now);
        if ($event === null) {
            throw new \DomainException('event_unavailable');
        }

        return $this->attendanceService->withdraw($grant->roundId, $event->id, $now);
    }
}
