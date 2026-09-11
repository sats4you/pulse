<?php
declare(strict_types=1);
namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Attendance\{AttendanceService, EventSnapshot};
use Sats4you\Pulse\Pulse\Event\{EventAccessPolicy, EventTiming, PublicationState};
use Sats4you\Pulse\Pulse\PublicAccess\{AccessGrant, AccessRole, ParticipantFlow, PublishedEvent};
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\{InMemoryAttendanceStore, InMemoryPublishedEventStore};
final class ParticipantFlowTest extends TestCase
{
    public function testParticipantLinkCanAddAndRemoveWithoutBrowserSpecificState(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z'); $roundId = '0123456789abcdef0123456789abcdef'; $eventId = '11111111111111111111111111111111'; $publicId = '22222222222222222222222222222222'; $timing = new EventTiming($now->modify('+12 days'), null);
        $attendance = new InMemoryAttendanceStore(); $attendance->events[$eventId] = new EventSnapshot($eventId, $roundId, PublicationState::Published, $now->modify('-1 day'), $timing, null);
        $events = new InMemoryPublishedEventStore(); $events->events[$publicId] = new PublishedEvent($eventId, $publicId, 'Bern Monthly Bitcoin Meetup', $timing, null, null, PublicationState::Published, $now->modify('-1 day'), null, null, 0);
        $flow = new ParticipantFlow($events, new AttendanceService($attendance, new EventAccessPolicy(), new RetentionSchedule())); $grant = new AccessGrant($roundId, AccessRole::Participant, 1);
        self::assertSame(1, $flow->join($grant, $publicId, $now)->count); self::assertTrue($flow->withdraw($grant, $publicId, $now)->withdrawn); self::assertSame(0, $attendance->countCommitments($eventId));
    }
}
