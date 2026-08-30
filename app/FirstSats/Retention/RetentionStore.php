<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Retention;

use DateTimeImmutable;

interface RetentionStore
{
    /** @return array{closed:int,payment_requests_deleted:int} */
    public function closeDueHandovers(DateTimeImmutable $now):array;
    public function scheduleDueInvitations(DateTimeImmutable $now):int;
    public function deleteDueInvitations(DateTimeImmutable $now):int;
}
