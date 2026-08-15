<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Retention;

use DateTimeImmutable;

interface RetentionStore
{
    public function deleteDueAttendance(DateTimeImmutable $now): int;

    public function deleteDueEvents(DateTimeImmutable $now): int;
}
