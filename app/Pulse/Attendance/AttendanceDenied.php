<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DomainException;

final class AttendanceDenied extends DomainException
{
    public static function eventUnavailable(): self
    {
        return new self('event_unavailable');
    }

    public static function newRsvpClosed(): self
    {
        return new self('new_rsvp_closed');
    }

    public static function withdrawalClosed(): self
    {
        return new self('withdrawal_closed');
    }
}
