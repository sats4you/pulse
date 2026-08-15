<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

use DateTimeImmutable;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class AdminEvent
{
    public function __construct(
        public string $id,
        public string $publicId,
        public string $roundId,
        public EventDetails $details,
        public PublicationState $publicationState,
        public ?DateTimeImmutable $publishAt,
        public ?DateTimeImmutable $rsvpClosedAt,
        public ?DateTimeImmutable $materialChangedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public DateTimeImmutable $deleteAt,
        public int $attendanceCount,
    ) {
    }
}
