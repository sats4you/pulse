<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Event;

use DateTimeImmutable;

final class EventAccessPolicy
{
    public function isVisible(
        PublicationState $state,
        ?DateTimeImmutable $publishAt,
        DateTimeImmutable $now,
    ): bool {
        return match ($state) {
            PublicationState::Published => true,
            PublicationState::Scheduled => $publishAt !== null && $publishAt <= $now,
            PublicationState::Cancelled => $publishAt !== null && $publishAt <= $now,
            PublicationState::Draft => false,
        };
    }

    public function acceptsNewRsvp(
        PublicationState $state,
        ?DateTimeImmutable $publishAt,
        EventTiming $timing,
        ?DateTimeImmutable $rsvpClosedAt,
        DateTimeImmutable $now,
    ): bool {
        if (!$this->isVisible($state, $publishAt, $now) || $state === PublicationState::Cancelled) {
            return false;
        }

        if ($now >= $timing->startsAt) {
            return false;
        }

        return $rsvpClosedAt === null || $now < $rsvpClosedAt;
    }

    public function acceptsWithdrawal(EventTiming $timing, DateTimeImmutable $now): bool
    {
        return $now <= $timing->effectiveEnd();
    }
}
