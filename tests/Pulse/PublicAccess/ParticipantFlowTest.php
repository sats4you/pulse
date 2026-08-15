<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Attendance\EventSnapshot;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantFlow;
use Sats4you\Pulse\Pulse\PublicAccess\PublishedEvent;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\InMemoryAttendanceStore;
use Sats4you\Pulse\Tests\Support\InMemoryPublishedEventStore;

final class ParticipantFlowTest extends TestCase
{
    public function testJoinStatusAndWithdrawalUsePublicEventWithoutExposingSecret(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $roundId = '0123456789abcdef0123456789abcdef';
        $eventId = '11111111111111111111111111111111';
        $publicId = '22222222222222222222222222222222';
        $timing = new EventTiming($now->modify('+12 days'), null);
        $attendance = new InMemoryAttendanceStore();
        $attendance->events[$eventId] = new EventSnapshot(
            $eventId,
            $roundId,
            PublicationState::Published,
            $now->modify('-1 day'),
            $timing,
            null,
        );
        $events = new InMemoryPublishedEventStore();
        $events->events[$publicId] = new PublishedEvent(
            $eventId,
            $publicId,
            'Bern Monthly Bitcoin Meetup',
            $timing,
            null,
            null,
            PublicationState::Published,
            $now->modify('-1 day'),
            null,
            null,
            0,
        );
        $digester = new SecretDigester(str_repeat('h', 32));
        $flow = new ParticipantFlow(
            $events,
            $attendance,
            new AttendanceService(
                $attendance,
                new EventAccessPolicy(),
                new RetentionSchedule(),
                new SecretGenerator(),
                $digester,
            ),
            $digester,
        );
        $grant = new AccessGrant($roundId, AccessRole::Participant, 1);

        $joined = $flow->join($grant, $publicId, null, $now);
        self::assertSame([$publicId => true], $flow->joinedByPublicId(
            $flow->events($grant, $now),
            [$publicId => $joined->participantSecret],
        ));

        $withdrawn = $flow->withdraw($grant, $publicId, $joined->participantSecret, $now);
        self::assertTrue($withdrawn->withdrawn);
        self::assertSame([$publicId => false], $flow->joinedByPublicId(
            $flow->events($grant, $now),
            [$publicId => $joined->participantSecret],
        ));
    }
}
