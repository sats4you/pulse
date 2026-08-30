<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Retention;

final readonly class RetentionReport
{
    public function __construct(
        public int $handoversClosed,
        public int $paymentRequestsDeleted,
        public int $invitationsScheduled,
        public int $invitationsDeleted,
    ) {}
}
