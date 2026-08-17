<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

final readonly class NotificationDispatchReport
{
    public function __construct(
        public int $sent,
        public int $failed,
        public int $expired,
        public bool $lockAcquired,
    ) {
    }
}
