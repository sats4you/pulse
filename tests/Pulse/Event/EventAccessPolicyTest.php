<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final class EventAccessPolicyTest extends TestCase
{
    private EventAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new EventAccessPolicy();
    }

    public static function visibilityCases(): iterable
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');

        yield 'draft hidden' => [PublicationState::Draft, null, $now, false];
        yield 'future schedule hidden' => [PublicationState::Scheduled, $now->modify('+1 hour'), $now, false];
        yield 'due schedule visible' => [PublicationState::Scheduled, $now, $now, true];
        yield 'published visible' => [PublicationState::Published, null, $now, true];
        yield 'previously published cancellation visible' => [PublicationState::Cancelled, $now->modify('-1 day'), $now, true];
    }

    #[DataProvider('visibilityCases')]
    public function testVisibility(
        PublicationState $state,
        ?DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
        bool $expected,
    ): void {
        self::assertSame($expected, $this->policy->isVisible($state, $publishAt, $now));
    }

    public function testNewRsvpClosesAtStart(): void
    {
        $start = new DateTimeImmutable('2026-08-27T16:30:00Z');
        $timing = new EventTiming($start, null);

        self::assertTrue($this->policy->acceptsNewRsvp(
            PublicationState::Published,
            null,
            $timing,
            null,
            $start->modify('-1 second'),
        ));
        self::assertFalse($this->policy->acceptsNewRsvp(
            PublicationState::Published,
            null,
            $timing,
            null,
            $start,
        ));
    }

    public function testManualClosureAndCancellationRejectNewRsvp(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $timing = new EventTiming($now->modify('+10 days'), null);

        self::assertFalse($this->policy->acceptsNewRsvp(
            PublicationState::Published,
            null,
            $timing,
            $now,
            $now,
        ));
        self::assertFalse($this->policy->acceptsNewRsvp(
            PublicationState::Cancelled,
            $now->modify('-1 day'),
            $timing,
            null,
            $now,
        ));
    }

    public function testWithdrawalRemainsPossibleUntilEffectiveEnd(): void
    {
        $start = new DateTimeImmutable('2026-08-27T16:30:00Z');
        $timing = new EventTiming($start, null);

        self::assertTrue($this->policy->acceptsWithdrawal($timing, $start->modify('+6 hours')));
        self::assertFalse($this->policy->acceptsWithdrawal($timing, $start->modify('+6 hours +1 second')));
    }
}
