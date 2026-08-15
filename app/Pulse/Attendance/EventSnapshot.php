<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class EventSnapshot
{
    public function __construct(
        public string $id,
        public string $roundId,
        public PublicationState $publicationState,
        public ?DateTimeImmutable $publishAt,
        public EventTiming $timing,
        public ?DateTimeImmutable $rsvpClosedAt,
    ) {
    }
}
