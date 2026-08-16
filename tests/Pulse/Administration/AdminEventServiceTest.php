<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Administration;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\Administration\AdminEventService;
use Sats4you\Pulse\Pulse\Administration\EventDetails;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\InMemoryAdminEventStore;

final class AdminEventServiceTest extends TestCase
{
    public function testPublishedEventCanBeChangedClosedCancelledAndDuplicated(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $store = new InMemoryAdminEventStore();
        $service = new AdminEventService($store, new RetentionSchedule());
        $grant = $this->administrator();
        $details = new EventDetails('Bern Monthly Bitcoin Meetup', $now->modify('+12 days'), null, null, null);

        $event = $service->create($grant, $details, PublicationState::Published, null, $now);
        self::assertSame(PublicationState::Published, $event->publicationState);
        self::assertSame($now, $event->publishAt);

        $changed = $service->update($grant, $event->publicId, new EventDetails(
            $details->title,
            $details->timing->startsAt->modify('+1 day'),
            null,
            'Restaurant in Bern',
            'Tisch reserviert.',
        ), $now->modify('+1 hour'));
        self::assertEquals($now->modify('+1 hour'), $changed->materialChangedAt);
        self::assertEquals($changed->details->timing->effectiveEnd()->modify('+30 days'), $changed->deleteAt);

        $closed = $service->closeRsvps($grant, $event->publicId, $now->modify('+2 hours'));
        self::assertEquals($now->modify('+2 hours'), $closed->rsvpClosedAt);

        $reopened = $service->openRsvps($grant, $event->publicId, $now->modify('+2 hours 30 minutes'));
        self::assertNull($reopened->rsvpClosedAt);

        $cancelled = $service->cancel($grant, $event->publicId, $now->modify('+3 hours'));
        self::assertSame(PublicationState::Cancelled, $cancelled->publicationState);

        $copy = $service->duplicate($grant, $event->publicId, $now->modify('+4 hours'));
        self::assertSame(PublicationState::Draft, $copy->publicationState);
        self::assertNotSame($event->publicId, $copy->publicId);
    }

    public function testScheduledPublicationMustBeInFuture(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $service = new AdminEventService(new InMemoryAdminEventStore(), new RetentionSchedule());

        $this->expectException(DomainException::class);
        $service->create(
            $this->administrator(),
            new EventDetails('Termin', $now->modify('+1 day'), null, null, null),
            PublicationState::Scheduled,
            $now,
            $now,
        );
    }

    public function testAutomaticallyVisibleScheduledEventCanBeCancelled(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $service = new AdminEventService(new InMemoryAdminEventStore(), new RetentionSchedule());
        $event = $service->create(
            $this->administrator(),
            new EventDetails('Termin', $now->modify('+10 days'), null, null, null),
            PublicationState::Scheduled,
            $now->modify('+1 hour'),
            $now,
        );

        $cancelled = $service->cancel($this->administrator(), $event->publicId, $now->modify('+2 hours'));

        self::assertSame(PublicationState::Cancelled, $cancelled->publicationState);
    }

    public function testRsvpsCannotBeReopenedAtOrAfterEventStart(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $service = new AdminEventService(new InMemoryAdminEventStore(), new RetentionSchedule());
        $event = $service->create(
            $this->administrator(),
            new EventDetails('Termin', $now->modify('+2 hours'), null, null, null),
            PublicationState::Published,
            null,
            $now,
        );
        $service->closeRsvps($this->administrator(), $event->publicId, $now->modify('+1 hour'));

        $this->expectException(DomainException::class);
        $service->openRsvps($this->administrator(), $event->publicId, $now->modify('+2 hours'));
    }

    public function testDraftAndNotYetVisibleScheduledEventCanBeDeleted(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $store = new InMemoryAdminEventStore();
        $service = new AdminEventService($store, new RetentionSchedule());
        $details = new EventDetails('Termin', $now->modify('+10 days'), null, null, null);

        $draft = $service->create($this->administrator(), $details, PublicationState::Draft, null, $now);
        $scheduled = $service->create(
            $this->administrator(),
            $details,
            PublicationState::Scheduled,
            $now->modify('+1 day'),
            $now,
        );

        $service->deleteUnpublished($this->administrator(), $draft->publicId, $now);
        $service->deleteUnpublished($this->administrator(), $scheduled->publicId, $now);

        self::assertSame([], $store->events);
    }

    public function testPublishedEventCannotBeDeletedManually(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $store = new InMemoryAdminEventStore();
        $service = new AdminEventService($store, new RetentionSchedule());
        $event = $service->create(
            $this->administrator(),
            new EventDetails('Termin', $now->modify('+10 days'), null, null, null),
            PublicationState::Published,
            null,
            $now,
        );

        try {
            $service->deleteUnpublished($this->administrator(), $event->publicId, $now);
            self::fail('Published event deletion must be rejected.');
        } catch (DomainException) {
            self::assertArrayHasKey($event->publicId, $store->events);
        }
    }

    public function testParticipantCannotReadAdministration(): void
    {
        $service = new AdminEventService(new InMemoryAdminEventStore(), new RetentionSchedule());

        $this->expectException(DomainException::class);
        $service->events(new AccessGrant(str_repeat('a', 32), AccessRole::Participant, 1));
    }

    private function administrator(): AccessGrant
    {
        return new AccessGrant(str_repeat('a', 32), AccessRole::Administrator, 1);
    }
}
