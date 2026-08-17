<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

final readonly class NotificationMessage
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {
    }
}
