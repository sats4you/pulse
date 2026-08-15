<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Retention;

use DateTimeImmutable;

final readonly class RetentionService
{
    public function __construct(private RetentionStore $store)
    {
    }

    public function run(DateTimeImmutable $now): RetentionReport
    {
        return new RetentionReport(
            $this->store->deleteDueAttendance($now),
            $this->store->deleteDueEvents($now),
        );
    }
}
