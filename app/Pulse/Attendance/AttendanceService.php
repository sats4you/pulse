<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;

final readonly class AttendanceService
{
    public function __construct(
        private AttendanceStore $store,
        private EventAccessPolicy $policy,
        private RetentionSchedule $retention,
        private SecretGenerator $secretGenerator,
        private SecretDigester $secretDigester,
    ) {
    }

    public function join(
        string $roundId,
        string $eventId,
        ?string $existingParticipantSecret,
        DateTimeImmutable $now,
    ): JoinResult {
        return $this->store->transaction(function () use ($roundId, $eventId, $existingParticipantSecret, $now): JoinResult {
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

            if ($existingParticipantSecret !== null && $existingParticipantSecret !== '') {
                $existingDigest = $this->secretDigester->digest($existingParticipantSecret);
                if ($this->store->hasCommitment($eventId, $existingDigest)) {
                    return new JoinResult(
                        $existingParticipantSecret,
                        $this->store->countCommitments($eventId),
                        true,
                        $this->retention->attendanceDeleteAt($event->timing),
                    );
                }
            }

            $participantSecret = $this->secretGenerator->generate();
            $this->store->insertCommitment(
                $eventId,
                $this->secretDigester->digest($participantSecret),
                $now,
                $this->retention->attendanceDeleteAt($event->timing),
            );
            $this->store->recordNotificationChange($eventId, 'join', $now);

            return new JoinResult(
                $participantSecret,
                $this->store->countCommitments($eventId),
                false,
                $this->retention->attendanceDeleteAt($event->timing),
            );
        });
    }

    public function withdraw(
        string $roundId,
        string $eventId,
        string $participantSecret,
        DateTimeImmutable $now,
    ): WithdrawResult {
        return $this->store->transaction(function () use ($roundId, $eventId, $participantSecret, $now): WithdrawResult {
            $event = $this->store->getEventForUpdate($roundId, $eventId);
            if ($event === null) {
                throw AttendanceDenied::eventUnavailable();
            }

            if (!$this->policy->acceptsWithdrawal($event->timing, $now)) {
                throw AttendanceDenied::withdrawalClosed();
            }

            $withdrawn = $this->store->deleteCommitment(
                $eventId,
                $this->secretDigester->digest($participantSecret),
            );
            if ($withdrawn) {
                $this->store->recordNotificationChange($eventId, 'withdraw', $now);
            }

            return new WithdrawResult($this->store->countCommitments($eventId), $withdrawn);
        });
    }
}
