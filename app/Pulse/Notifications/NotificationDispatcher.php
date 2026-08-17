<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

use DateInterval;
use DateTimeImmutable;
use Throwable;

final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationOutboxStore $store,
        private NotificationMailer $mailer,
        private NotificationMessageFactory $messages,
        private DateInterval $debounce,
    ) {
    }

    public function dispatch(DateTimeImmutable $now): NotificationDispatchReport
    {
        if (!$this->store->acquireDispatchLock()) {
            return new NotificationDispatchReport(0, 0, 0, false);
        }

        $sent = 0;
        $failed = 0;
        $expired = 0;
        try {
            $expired = $this->store->deleteExpired($now);
            foreach ($this->store->pendingBatches($now->sub($this->debounce)) as $batch) {
                try {
                    if (!$this->mailer->send($this->messages->create($batch))) {
                        ++$failed;
                        continue;
                    }

                    $this->store->deleteChanges($batch->changeIds);
                    ++$sent;
                } catch (Throwable) {
                    ++$failed;
                }
            }
        } finally {
            $this->store->releaseDispatchLock();
        }

        return new NotificationDispatchReport($sent, $failed, $expired, true);
    }
}
