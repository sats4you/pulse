<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

final readonly class PhpMailNotificationMailer implements NotificationMailer
{
    public function __construct(
        private string $recipient,
        private string $from,
    ) {
        foreach ([$this->recipient, $this->from] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $address) === 1) {
                throw new \InvalidArgumentException('Notification mail address is invalid.');
            }
        }
    }

    public function send(NotificationMessage $message): bool
    {
        $subject = '=?UTF-8?B?' . base64_encode($message->subject) . '?=';
        $headers = implode("\r\n", [
            'From: pulse <' . $this->from . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
        ]);

        return mail($this->recipient, $subject, $message->body, $headers);
    }
}
