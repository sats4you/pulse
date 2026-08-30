<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Payment;

interface PaymentRequestStore
{
    public function find(string $invitationId): ?PaymentRequest;
    public function save(PaymentRequest $request): void;
}
