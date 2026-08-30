<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Payment;

use DateTimeImmutable;

final readonly class PaymentRequest
{
    public function __construct(
        public string $invitationId,
        public string $bolt11,
        public int $satsAmount,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $submittedAt,
    ) {
    }
}
