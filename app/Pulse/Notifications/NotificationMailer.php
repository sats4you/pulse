<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

interface NotificationMailer
{
    public function send(NotificationMessage $message): bool;
}
