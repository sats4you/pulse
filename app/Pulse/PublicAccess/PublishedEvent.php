<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class PublishedEvent
{
    public function __construct(
        public string $id,
        public string $publicId,
        public string $title,
        public EventTiming $timing,
        public ?string $location,
        public ?string $note,
        public PublicationState $publicationState,
        public ?DateTimeImmutable $publishAt,
        public ?DateTimeImmutable $rsvpClosedAt,
        public ?DateTimeImmutable $materialChangedAt,
        public int $attendanceCount,
    ) {
    }
}
