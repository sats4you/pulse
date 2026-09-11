<?php
declare(strict_types=1);
namespace Sats4you\Pulse\Tests\Pulse\Attendance;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Attendance\{AttendanceDenied, AttendanceService, EventSnapshot};
use Sats4you\Pulse\Pulse\Event\{EventAccessPolicy, EventTiming, PublicationState};
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\InMemoryAttendanceStore;
final class AttendanceServiceTest extends TestCase
{
    private const ROUND_ID = 'round-bern'; private const EVENT_ID = 'event-august';
    private InMemoryAttendanceStore $store; private AttendanceService $service; private DateTimeImmutable $now;
    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-15T12:00:00Z'); $this->store = new InMemoryAttendanceStore();
        $this->store->events[self::EVENT_ID] = new EventSnapshot(self::EVENT_ID, self::ROUND_ID, PublicationState::Published, $this->now->modify('-1 day'), new EventTiming($this->now->modify('+12 days'), null), null);
        $this->service = new AttendanceService($this->store, new EventAccessPolicy(), new RetentionSchedule());
    }
    public function testEveryLinkHolderCanAddAnAnonymousCommitment(): void
    {
        self::assertSame(1, $this->service->join(self::ROUND_ID, self::EVENT_ID, $this->now)->count);
        self::assertSame(2, $this->service->join(self::ROUND_ID, self::EVENT_ID, $this->now->modify('+1 minute'))->count);
        self::assertCount(2, $this->store->commitments[self::EVENT_ID]);
        self::assertSame(['join', 'join'], array_column($this->store->notificationChanges, 'changeType'));
    }
    public function testAnyLinkHolderCanRemoveOneCommitmentButNeverBelowZero(): void
    {
        $this->service->join(self::ROUND_ID, self::EVENT_ID, $this->now); $this->service->join(self::ROUND_ID, self::EVENT_ID, $this->now->modify('+1 minute'));
        $first = $this->service->withdraw(self::ROUND_ID, self::EVENT_ID, $this->now->modify('+2 minutes')); $second = $this->service->withdraw(self::ROUND_ID, self::EVENT_ID, $this->now->modify('+3 minutes')); $empty = $this->service->withdraw(self::ROUND_ID, self::EVENT_ID, $this->now->modify('+4 minutes'));
        self::assertTrue($first->withdrawn); self::assertSame(1, $first->count); self::assertTrue($second->withdrawn); self::assertSame(0, $second->count); self::assertFalse($empty->withdrawn); self::assertSame(0, $empty->count);
        self::assertSame(['join', 'join', 'withdraw', 'withdraw'], array_column($this->store->notificationChanges, 'changeType'));
    }
    public function testWrongRoundCannotAccessEvent(): void
    {
        $this->expectException(AttendanceDenied::class); $this->expectExceptionMessage('event_unavailable'); $this->service->join('round-other', self::EVENT_ID, $this->now);
    }
    public function testCancelledEventRejectsNewRsvp(): void
    {
        $event = $this->store->events[self::EVENT_ID]; $this->store->events[self::EVENT_ID] = new EventSnapshot($event->id, $event->roundId, PublicationState::Cancelled, $event->publishAt, $event->timing, $event->rsvpClosedAt);
        $this->expectException(AttendanceDenied::class); $this->expectExceptionMessage('new_rsvp_closed'); $this->service->join(self::ROUND_ID, self::EVENT_ID, $this->now);
    }
}
