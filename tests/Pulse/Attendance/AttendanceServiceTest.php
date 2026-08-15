<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Attendance;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Attendance\AttendanceDenied;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Attendance\EventSnapshot;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\InMemoryAttendanceStore;

final class AttendanceServiceTest extends TestCase
{
    private const ROUND_ID = 'round-bern';
    private const EVENT_ID = 'event-august';

    private InMemoryAttendanceStore $store;
    private AttendanceService $service;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $this->store = new InMemoryAttendanceStore();
        $this->store->events[self::EVENT_ID] = new EventSnapshot(
            self::EVENT_ID,
            self::ROUND_ID,
            PublicationState::Published,
            $this->now->modify('-1 day'),
            new EventTiming($this->now->modify('+12 days'), null),
            null,
        );
        $this->service = new AttendanceService(
            $this->store,
            new EventAccessPolicy(),
            new RetentionSchedule(),
            new SecretGenerator(),
            new SecretDigester(str_repeat('h', 32)),
        );
    }

    public function testFirstJoinCreatesOneAnonymousCommitment(): void
    {
        $result = $this->service->join(self::ROUND_ID, self::EVENT_ID, null, $this->now);

        self::assertSame(1, $result->count);
        self::assertFalse($result->alreadyJoined);
        self::assertSame(43, strlen($result->participantSecret));
        self::assertCount(1, $this->store->commitments[self::EVENT_ID]);
    }

    public function testRepeatedJoinWithSameSecretIsIdempotent(): void
    {
        $first = $this->service->join(self::ROUND_ID, self::EVENT_ID, null, $this->now);
        $second = $this->service->join(
            self::ROUND_ID,
            self::EVENT_ID,
            $first->participantSecret,
            $this->now->modify('+1 minute'),
        );

        self::assertTrue($second->alreadyJoined);
        self::assertSame($first->participantSecret, $second->participantSecret);
        self::assertSame(1, $second->count);
        self::assertCount(1, $this->store->commitments[self::EVENT_ID]);
    }

    public function testSecretForOneEventCannotWithdrawFromAnotherEvent(): void
    {
        $first = $this->service->join(self::ROUND_ID, self::EVENT_ID, null, $this->now);
        $otherId = 'event-september';
        $this->store->events[$otherId] = new EventSnapshot(
            $otherId,
            self::ROUND_ID,
            PublicationState::Published,
            $this->now->modify('-1 day'),
            new EventTiming($this->now->modify('+40 days'), null),
            null,
        );

        $result = $this->service->withdraw(
            self::ROUND_ID,
            $otherId,
            $first->participantSecret,
            $this->now,
        );

        self::assertFalse($result->withdrawn);
        self::assertSame(0, $result->count);
        self::assertSame(1, $this->store->countCommitments(self::EVENT_ID));
    }

    public function testWithdrawalDeletesCommitmentImmediately(): void
    {
        $joined = $this->service->join(self::ROUND_ID, self::EVENT_ID, null, $this->now);
        $result = $this->service->withdraw(
            self::ROUND_ID,
            self::EVENT_ID,
            $joined->participantSecret,
            $this->now->modify('+1 hour'),
        );

        self::assertTrue($result->withdrawn);
        self::assertSame(0, $result->count);
        self::assertCount(0, $this->store->commitments[self::EVENT_ID]);
    }

    public function testWrongRoundCannotAccessEvent(): void
    {
        $this->expectException(AttendanceDenied::class);
        $this->expectExceptionMessage('event_unavailable');

        $this->service->join('round-other', self::EVENT_ID, null, $this->now);
    }

    public function testCancelledEventRejectsNewRsvp(): void
    {
        $event = $this->store->events[self::EVENT_ID];
        $this->store->events[self::EVENT_ID] = new EventSnapshot(
            $event->id,
            $event->roundId,
            PublicationState::Cancelled,
            $event->publishAt,
            $event->timing,
            $event->rsvpClosedAt,
        );

        $this->expectException(AttendanceDenied::class);
        $this->expectExceptionMessage('new_rsvp_closed');

        $this->service->join(self::ROUND_ID, self::EVENT_ID, null, $this->now);
    }
}
