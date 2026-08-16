<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

use DateTimeImmutable;
use DomainException;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;

final readonly class AdminEventService
{
    public function __construct(
        private AdminEventStore $store,
        private RetentionSchedule $retention,
    ) {
    }

    /** @return list<AdminEvent> */
    public function events(AccessGrant $grant): array
    {
        $this->assertAdministrator($grant);

        return $this->store->listAll($grant->roundId);
    }

    public function find(AccessGrant $grant, string $publicEventId): ?AdminEvent
    {
        $this->assertAdministrator($grant);

        return $this->store->find($grant->roundId, $publicEventId);
    }

    public function create(
        AccessGrant $grant,
        EventDetails $details,
        PublicationState $state,
        ?DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
    ): AdminEvent {
        $this->assertAdministrator($grant);
        $publishAt = $this->normalisePublication($state, $publishAt, $now);
        $event = new AdminEvent(
            bin2hex(random_bytes(16)),
            bin2hex(random_bytes(16)),
            $grant->roundId,
            $details,
            $state,
            $publishAt,
            null,
            null,
            $now,
            $now,
            $this->retention->eventDeleteAt($details->timing),
            0,
        );
        $this->store->insert($event);

        return $event;
    }

    public function update(
        AccessGrant $grant,
        string $publicEventId,
        EventDetails $details,
        DateTimeImmutable $now,
    ): AdminEvent {
        $existing = $this->required($grant, $publicEventId);
        $wasVisible = $existing->publicationState === PublicationState::Published
            || (($existing->publicationState === PublicationState::Scheduled
                || $existing->publicationState === PublicationState::Cancelled)
                && $existing->publishAt !== null
                && $existing->publishAt <= $now);
        $materiallyChanged = $wasVisible
            && ($existing->details->timing != $details->timing || $existing->details->location !== $details->location);
        $updated = new AdminEvent(
            $existing->id,
            $existing->publicId,
            $existing->roundId,
            $details,
            $existing->publicationState,
            $existing->publishAt,
            $existing->rsvpClosedAt,
            $materiallyChanged ? $now : $existing->materialChangedAt,
            $existing->createdAt,
            $now,
            $this->retention->eventDeleteAt($details->timing),
            $existing->attendanceCount,
        );
        $this->store->save($updated);

        return $updated;
    }

    public function publish(AccessGrant $grant, string $publicEventId, DateTimeImmutable $now): AdminEvent
    {
        $event = $this->required($grant, $publicEventId);
        if ($event->publicationState === PublicationState::Cancelled) {
            throw new DomainException('cancelled_event_cannot_be_published');
        }

        return $this->withPublication($event, PublicationState::Published, $now, $now);
    }

    public function schedule(
        AccessGrant $grant,
        string $publicEventId,
        DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
    ): AdminEvent {
        $event = $this->required($grant, $publicEventId);
        $alreadyVisible = $event->publicationState === PublicationState::Published
            || ($event->publicationState === PublicationState::Scheduled
                && $event->publishAt !== null
                && $event->publishAt <= $now);
        if ($event->publicationState === PublicationState::Cancelled || $alreadyVisible) {
            throw new DomainException('visible_event_cannot_be_scheduled');
        }

        return $this->withPublication(
            $event,
            PublicationState::Scheduled,
            $this->normalisePublication(PublicationState::Scheduled, $publishAt, $now),
            $now,
        );
    }

    public function cancel(AccessGrant $grant, string $publicEventId, DateTimeImmutable $now): AdminEvent
    {
        $event = $this->required($grant, $publicEventId);
        $isVisible = $event->publicationState === PublicationState::Published
            || ($event->publicationState === PublicationState::Scheduled
                && $event->publishAt !== null
                && $event->publishAt <= $now);
        if (!$isVisible) {
            throw new DomainException('only_published_event_can_be_cancelled');
        }

        return $this->withPublication($event, PublicationState::Cancelled, $event->publishAt ?? $now, $now, $now);
    }

    public function closeRsvps(AccessGrant $grant, string $publicEventId, DateTimeImmutable $now): AdminEvent
    {
        $event = $this->required($grant, $publicEventId);
        $isVisible = $event->publicationState === PublicationState::Published
            || ($event->publicationState === PublicationState::Scheduled
                && $event->publishAt !== null
                && $event->publishAt <= $now);
        if (!$isVisible || $now >= $event->details->timing->startsAt) {
            throw new DomainException('rsvp_cannot_be_closed');
        }

        $updated = new AdminEvent(
            $event->id,
            $event->publicId,
            $event->roundId,
            $event->details,
            $event->publicationState,
            $event->publishAt,
            $now,
            $event->materialChangedAt,
            $event->createdAt,
            $now,
            $event->deleteAt,
            $event->attendanceCount,
        );
        $this->store->save($updated);

        return $updated;
    }

    public function openRsvps(AccessGrant $grant, string $publicEventId, DateTimeImmutable $now): AdminEvent
    {
        $event = $this->required($grant, $publicEventId);
        $isVisible = $event->publicationState === PublicationState::Published
            || ($event->publicationState === PublicationState::Scheduled
                && $event->publishAt !== null
                && $event->publishAt <= $now);
        if (!$isVisible || $event->rsvpClosedAt === null || $now >= $event->details->timing->startsAt) {
            throw new DomainException('rsvp_cannot_be_opened');
        }

        $updated = new AdminEvent(
            $event->id,
            $event->publicId,
            $event->roundId,
            $event->details,
            $event->publicationState,
            $event->publishAt,
            null,
            $event->materialChangedAt,
            $event->createdAt,
            $now,
            $event->deleteAt,
            $event->attendanceCount,
        );
        $this->store->save($updated);

        return $updated;
    }

    public function duplicate(AccessGrant $grant, string $publicEventId, DateTimeImmutable $now): AdminEvent
    {
        $event = $this->required($grant, $publicEventId);

        return $this->create($grant, $event->details, PublicationState::Draft, null, $now);
    }

    private function required(AccessGrant $grant, string $publicEventId): AdminEvent
    {
        $this->assertAdministrator($grant);
        $event = $this->store->find($grant->roundId, $publicEventId);
        if ($event === null) {
            throw new DomainException('event_not_found');
        }

        return $event;
    }

    private function withPublication(
        AdminEvent $event,
        PublicationState $state,
        DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
        ?DateTimeImmutable $materialChangedAt = null,
    ): AdminEvent {
        $updated = new AdminEvent(
            $event->id,
            $event->publicId,
            $event->roundId,
            $event->details,
            $state,
            $publishAt,
            $event->rsvpClosedAt,
            $materialChangedAt ?? $event->materialChangedAt,
            $event->createdAt,
            $now,
            $event->deleteAt,
            $event->attendanceCount,
        );
        $this->store->save($updated);

        return $updated;
    }

    private function normalisePublication(
        PublicationState $state,
        ?DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
    ): ?DateTimeImmutable {
        return match ($state) {
            PublicationState::Draft => null,
            PublicationState::Published => $now,
            PublicationState::Scheduled => $publishAt !== null && $publishAt > $now
                ? $publishAt
                : throw new DomainException('scheduled_publication_must_be_in_the_future'),
            PublicationState::Cancelled => throw new DomainException('event_cannot_be_created_cancelled'),
        };
    }

    private function assertAdministrator(AccessGrant $grant): void
    {
        if ($grant->role !== AccessRole::Administrator) {
            throw new DomainException('administrator_required');
        }
    }
}
