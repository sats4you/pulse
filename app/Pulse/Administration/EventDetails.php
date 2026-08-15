<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

use DateTimeImmutable;
use InvalidArgumentException;
use Sats4you\Pulse\Pulse\Event\EventTiming;

final readonly class EventDetails
{
    public string $title;
    public ?string $location;
    public ?string $note;
    public EventTiming $timing;

    public function __construct(
        string $title,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        ?string $location,
        ?string $note,
    ) {
        $title = trim($title);
        $location = self::optional($location);
        $note = self::optional($note);
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Event title must contain between 1 and 180 characters.');
        }
        if ($location !== null && mb_strlen($location) > 240) {
            throw new InvalidArgumentException('Event location must not exceed 240 characters.');
        }
        if ($note !== null && mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('Event note must not exceed 1000 characters.');
        }

        $this->title = $title;
        $this->location = $location;
        $this->note = $note;
        $this->timing = new EventTiming($startsAt, $endsAt);
    }

    private static function optional(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
