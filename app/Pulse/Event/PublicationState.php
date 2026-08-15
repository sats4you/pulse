<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Event;

enum PublicationState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';
}
