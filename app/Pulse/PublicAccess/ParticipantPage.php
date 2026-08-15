<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use IntlDateFormatter;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ParticipantPage
{
    public function __construct(private EventAccessPolicy $accessPolicy)
    {
    }

    /**
     * @param list<PublishedEvent> $events
     * @param array<string, bool> $joinedByPublicId
     * @param array<string, string> $languageNames
     */
    public function render(
        TranslatorInterface $translator,
        string $locale,
        string $groupName,
        string $publicSlug,
        array $events,
        array $joinedByPublicId,
        array $languageNames,
        DateTimeImmutable $now,
    ): string {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key, array $parameters = []): string => htmlspecialchars(
            $translator->trans($key, $parameters),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $locale = in_array($locale, ['de', 'fr', 'it', 'rm'], true) ? $locale : 'de';
        $localeAttribute = $escape($locale);
        $group = $escape($groupName);
        $options = '';
        foreach ($languageNames as $code => $name) {
            $selected = $code === $locale ? ' selected' : '';
            $options .= sprintf('<option value="%s"%s>%s</option>', $escape($code), $selected, $escape($name));
        }

        $cards = '';
        foreach ($events as $event) {
            $joined = $joinedByPublicId[$event->publicId] ?? false;
            $cards .= $this->renderEvent($translator, $locale, $publicSlug, $event, $joined, $now);
        }
        if ($cards === '') {
            $cards = '<p class="empty">' . $t('participant.empty') . '</p>';
        }

        return <<<HTML
            <!doctype html>
            <html lang="{$localeAttribute}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>{$t('participant.heading')} · pulse</title>
                <link rel="stylesheet" href="/assets/pulse.css">
            </head>
            <body>
                <main class="shell">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch">
                            <span class="brandmark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                            <span>sats4you.ch</span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language" data-current-path="/pulse/r/{$escape($publicSlug)}/events">{$options}</select>
                    </div>
                    <p class="eyebrow">pulse · {$group}</p>
                    <h1>{$t('participant.heading')}</h1>
                    <p class="lead">{$t('privacy.short')}</p>
                    <p><a class="privacy-link" href="/pulse/privacy?lang={$localeAttribute}">{$t('privacy.link')}</a></p>
                    <h2 class="section-title">{$t('participant.events')}</h2>
                    <div class="event-list">{$cards}</div>
                    <p class="footnote">{$t('pilot.label')} · {$t('participant.no_account')}</p>
                </main>
                <script src="/assets/participant-page.js" defer></script>
            </body>
            </html>
            HTML;
    }

    private function renderEvent(
        TranslatorInterface $translator,
        string $locale,
        string $publicSlug,
        PublishedEvent $event,
        bool $joined,
        DateTimeImmutable $now,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key, array $parameters = []): string => htmlspecialchars(
            $translator->trans($key, $parameters),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $date = $this->formatDate($event->timing->startsAt, $locale);
        $location = $event->location === null || trim($event->location) === ''
            ? $t('event.location_open')
            : $e($event->location);
        $countKey = match ($event->attendanceCount) {
            0 => 'rsvp.count_zero',
            1 => 'rsvp.count_one',
            default => 'rsvp.count_many',
        };
        $count = $t($countKey, ['%count%' => (string) $event->attendanceCount]);
        $badges = '';
        if ($event->publicationState === PublicationState::Cancelled) {
            $badges .= '<span class="status status-danger">' . $t('event.cancelled') . '</span>';
        }
        if ($event->materialChangedAt !== null) {
            $changed = $this->formatShortDate($event->materialChangedAt, $locale);
            $badges .= '<span class="status">' . $t('event.changed_at', ['%date%' => $changed]) . '</span>';
        }

        $note = $event->note === null || trim($event->note) === ''
            ? ''
            : '<p class="event-note">' . nl2br($e($event->note)) . '</p>';
        $includes = $joined ? '<p class="includes-you">' . $t('rsvp.count_includes_you') . '</p>' : '';
        $action = '';
        if ($joined && $this->accessPolicy->acceptsWithdrawal($event->timing, $now)) {
            $action = $this->form($publicSlug, $event->publicId, 'withdraw', $t('rsvp.withdraw'), 'secondary', $locale);
        } elseif (!$joined && $this->accessPolicy->acceptsNewRsvp(
            $event->publicationState,
            $event->publishAt,
            $event->timing,
            $event->rsvpClosedAt,
            $now,
        )) {
            $action = $this->form($publicSlug, $event->publicId, 'rsvp', $t('rsvp.join'), 'primary-button', $locale);
        } elseif (!$joined) {
            $action = '<p class="closed">' . $t('event.rsvp_closed') . '</p>';
        }

        return <<<HTML
            <article class="event-card">
                <div class="event-statuses">{$badges}</div>
                <div class="event-card-head">
                    <div>
                        <h3>{$e($event->title)}</h3>
                        <p>{$e($date)}<br>{$location}</p>
                    </div>
                    <div class="count"><strong>{$event->attendanceCount}</strong><span>{$count}</span></div>
                </div>
                {$note}
                {$includes}
                {$action}
            </article>
            HTML;
    }

    private function form(string $slug, string $publicId, string $operation, string $label, string $class, string $locale): string
    {
        $action = sprintf('/pulse/r/%s/events/%s/%s', rawurlencode($slug), rawurlencode($publicId), $operation);

        return sprintf(
            '<form method="post" action="%s"><input type="hidden" name="lang" value="%s"><button class="%s" type="submit">%s</button></form>',
            htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
            $label,
        );
    }

    private function formatDate(DateTimeImmutable $date, string $locale): string
    {
        return $this->formatter($locale, 'EEEE, d. MMMM y · HH:mm')->format($date) ?: $date->format('Y-m-d H:i');
    }

    private function formatShortDate(DateTimeImmutable $date, string $locale): string
    {
        return $this->formatter($locale, 'd. MMMM y, HH:mm')->format($date) ?: $date->format('Y-m-d H:i');
    }

    private function formatter(string $locale, string $pattern): IntlDateFormatter
    {
        $regionalLocale = match ($locale) {
            'fr' => 'fr_CH',
            'it' => 'it_CH',
            'rm' => 'rm_CH',
            default => 'de_CH',
        };

        return new IntlDateFormatter(
            $regionalLocale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Europe/Zurich',
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );
    }
}
