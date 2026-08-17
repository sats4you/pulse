<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

use DateTimeZone;
use IntlDateFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NotificationMessageFactory
{
    public function __construct(
        private TranslatorInterface $translator,
        private string $locale,
        private DateTimeZone $timezone,
    ) {
    }

    public function create(NotificationBatch $batch): NotificationMessage
    {
        $parameters = [
            '%event%' => $batch->eventTitle,
            '%joins%' => (string) $batch->joins,
            '%withdrawals%' => (string) $batch->withdrawals,
            '%count%' => (string) $batch->currentCount,
            '%date%' => $this->date($batch),
        ];

        $lines = [
            $this->translator->trans('notification.event', $parameters),
            $this->translator->trans('notification.date', $parameters),
            '',
            $this->translator->trans('notification.changes', $parameters),
            $this->translator->trans('notification.current_count', $parameters),
            '',
            $this->translator->trans('notification.privacy', $parameters),
        ];

        return new NotificationMessage(
            $this->translator->trans('notification.subject', $parameters),
            implode("\n", $lines) . "\n",
        );
    }

    private function date(NotificationBatch $batch): string
    {
        $formatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::SHORT,
            $this->timezone->getName(),
        );

        return $formatter->format($batch->startsAt) ?: $batch->startsAt->setTimezone($this->timezone)->format('d.m.Y H:i');
    }
}
