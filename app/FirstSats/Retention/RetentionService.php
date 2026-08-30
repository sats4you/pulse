<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Retention;

use DateTimeImmutable;

final readonly class RetentionService
{
    public function __construct(private RetentionStore $store){}

    public function run(DateTimeImmutable $now):RetentionReport
    {
        $closed=$this->store->closeDueHandovers($now);
        return new RetentionReport(
            $closed['closed'],$closed['payment_requests_deleted'],
            $this->store->scheduleDueInvitations($now),$this->store->deleteDueInvitations($now),
        );
    }
}
