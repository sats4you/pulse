<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;

final readonly class AttendanceService
{
    public function __construct(
        private AttendanceStore $store,
        private EventAccessPolicy $policy,
        private RetentionSchedule $retention,
    ) {
    }

    public function join(
        string $roundId,
        string $eventId,
        DateTimeImmutable $now,
    ): JoinResult {
        return $this->store->transaction(function () use ($roundId, $eventId, $now): JoinResult {
            $event = $this->store->getEventForUpdate($roundId, $eventId);
            if ($event === null) {
                throw AttendanceDenied::eventUnavailable();
            }

            if (!$this->policy->acceptsNewRsvp(
                $event->publicationState,
                $event->publishAt,
                $event->timing,
                $event->rsvpClosedAt,
                $now,
            )) {
                throw AttendanceDenied::newRsvpClosed();
            }

            $this->store->addCommitment(
                $eventId,
                $now,
                $this->retention->attendanceDeleteAt($event->timing),
            );
            $this->store->recordNotificationChange($eventId, 'join', $now);

            return new JoinResult(
                $this->store->countCommitments($eventId),
            );
        });
    }

    public function withdraw(
        string $roundId,
        string $eventId,
        DateTimeImmutable $now,
    ): WithdrawResult {
        return $this->store->transaction(function () use ($roundId, $eventId, $now): WithdrawResult {
            $event = $this->store->getEventForUpdate($roundId, $eventId);
            if ($event === null) {
                throw AttendanceDenied::eventUnavailable();
            }

            if (!$this->policy->acceptsWithdrawal($event->timing, $now)) {
                throw AttendanceDenied::withdrawalClosed();
            }

            $withdrawn = $this->store->removeAnyCommitment($eventId);
            if ($withdrawn) {
                $this->store->recordNotificationChange($eventId, 'withdraw', $now);
            }

            return new WithdrawResult($this->store->countCommitments($eventId), $withdrawn);
        });
    }
}
