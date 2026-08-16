<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminPage
{
    /**
     * @param list<AdminEvent> $events
     * @param array<string, string> $languageNames
     */
    public function render(
        TranslatorInterface $translator,
        string $locale,
        string $groupName,
        string $publicSlug,
        array $events,
        ?AdminEvent $editing,
        bool $creating,
        string $csrfToken,
        array $languageNames,
        ?string $notice,
        DateTimeImmutable $now,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key, array $parameters = []): string => htmlspecialchars(
            $translator->trans($key, $parameters),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $locale = in_array($locale, ['de', 'fr', 'it', 'rm'], true) ? $locale : 'de';
        $options = '';
        foreach ($languageNames as $code => $name) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $e($code),
                $code === $locale ? ' selected' : '',
                $e($name),
            );
        }
        $form = ($creating || $editing !== null)
            ? $this->eventForm($translator, $locale, $publicSlug, $editing, $csrfToken)
            : '';
        $cards = '';
        foreach ($events as $event) {
            $cards .= $this->eventCard($translator, $locale, $publicSlug, $event, $csrfToken, $now);
        }
        if ($cards === '') {
            $cards = '<p class="empty">' . $t('admin.empty') . '</p>';
        }
        $noticeHtml = $notice === null ? '' : '<p class="notice" role="status">' . $t($notice) . '</p>';
        $newPath = '/pulse/manage/r/' . rawurlencode($publicSlug) . '/events?new=1&amp;lang=' . rawurlencode($locale);
        $rotationPath = '/pulse/manage/r/' . rawurlencode($publicSlug) . '/participant-link/rotate';
        $credentials = <<<HTML
            <section class="credential-admin" aria-labelledby="credential-heading">
                <h2 id="credential-heading">{$t('credentials.heading')}</h2>
                <p>{$t('credentials.participant_explanation')}</p>
                <p class="warning">{$t('credentials.participant_warning')}</p>
                <form method="post" action="{$e($rotationPath)}">
                    <input type="hidden" name="csrf" value="{$e($csrfToken)}">
                    <input type="hidden" name="lang" value="{$e($locale)}">
                    <label class="confirmation"><input type="checkbox" name="confirm" value="rotate" required> <span>{$t('credentials.participant_confirm')}</span></label>
                    <button class="secondary" type="submit">{$t('credentials.participant_button')}</button>
                </form>
            </section>
            HTML;

        return <<<HTML
            <!doctype html>
            <html lang="{$e($locale)}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>{$t('admin.heading')} · pulse</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/pulse.css?v=20260816-mobile1">
            </head>
            <body>
                <main class="shell admin-shell">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch">
                            <img class="brand-symbol" src="/assets/sats4you-mark.svg" alt="">
                            <span class="brand-word">sats<span class="brand-four">4</span>you<span class="brand-tld">.ch</span></span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language" data-current-path="/pulse/manage/r/{$e($publicSlug)}/events">{$options}</select>
                    </div>
                    <p class="eyebrow">{$t('admin.eyebrow')}</p>
                    <div class="admin-title">
                        <div><h1>{$t('admin.heading')}</h1><p class="lead">{$e($groupName)}</p></div>
                        <a class="new-event" href="{$newPath}">{$t('admin.new_event')}</a>
                    </div>
                    {$noticeHtml}
                    {$form}
                    <div class="event-list admin-events">{$cards}</div>
                    {$credentials}
                    <aside class="privacy-admin">{$t('admin.privacy_notice')} <a href="/pulse/privacy?lang={$e($locale)}">{$t('privacy.link')}</a></aside>
                    <p class="footnote">{$t('pilot.label')} · {$t('admin.secret_notice')}</p>
                </main>
                <script src="/assets/participant-page.js?v=20260816-rsvp1" defer></script>
            </body>
            </html>
            HTML;
    }

    private function eventForm(
        TranslatorInterface $translator,
        string $locale,
        string $slug,
        ?AdminEvent $event,
        string $csrfToken,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key): string => htmlspecialchars($translator->trans($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = '/pulse/manage/r/' . rawurlencode($slug) . '/events';
        if ($event !== null) {
            $action .= '/' . rawurlencode($event->publicId);
        }
        $title = $event?->details->title ?? '';
        $startsAt = $this->inputDate($event?->details->timing->startsAt);
        $endsAt = $this->inputDate($event?->details->timing->endsAt);
        $location = $event?->details->location ?? '';
        $note = $event?->details->note ?? '';
        $publishAt = $this->inputDate($event?->publishAt);
        $heading = $event === null ? $t('admin.form_new') : $t('admin.form_edit');
        $saveLabel = $event === null ? $t('admin.save_draft') : $t('admin.save_changes');
        $canPublish = $event === null
            || $event->publicationState === PublicationState::Draft
            || $event->publicationState === PublicationState::Scheduled;
        $canSchedule = $event === null
            || $event->publicationState === PublicationState::Draft
            || ($event->publicationState === PublicationState::Scheduled
                && $event->publishAt !== null
                && $event->publishAt > new DateTimeImmutable());
        $scheduleButton = $canSchedule
            ? '<button class="secondary" name="intent" value="schedule" type="submit">' . $t('admin.schedule') . '</button>'
            : '';
        $publishButton = $canPublish
            ? '<button class="primary-button" name="intent" value="publish" type="submit">' . $t('admin.publish') . '</button>'
            : '';
        $cancelPath = '/pulse/manage/r/' . rawurlencode($slug) . '/events?lang=' . rawurlencode($locale);

        return <<<HTML
            <section class="editor" aria-labelledby="event-form-heading">
                <h2 id="event-form-heading">{$heading}</h2>
                <form method="post" action="{$e($action)}">
                    <input type="hidden" name="csrf" value="{$e($csrfToken)}">
                    <input type="hidden" name="lang" value="{$e($locale)}">
                    <label>{$t('admin.field_title')}<input name="title" maxlength="180" required value="{$e($title)}"></label>
                    <div class="form-grid">
                        <label>{$t('admin.field_start')}<input type="datetime-local" name="starts_at" required value="{$e($startsAt)}"></label>
                        <label>{$t('admin.field_end')}<input type="datetime-local" name="ends_at" value="{$e($endsAt)}"></label>
                    </div>
                    <label>{$t('admin.field_location')}<input name="location" maxlength="240" value="{$e($location)}"></label>
                    <label>{$t('admin.field_note')}<textarea name="note" maxlength="1000" rows="3">{$e($note)}</textarea></label>
                    <label>{$t('admin.field_publish_at')}<input type="datetime-local" name="publish_at" value="{$e($publishAt)}"></label>
                    <p class="field-help">{$t('admin.publish_help')}</p>
                    <div class="form-actions">
                        <button class="secondary" name="intent" value="save" type="submit">{$saveLabel}</button>
                        {$scheduleButton}
                        {$publishButton}
                        <a href="{$e($cancelPath)}">{$t('admin.form_cancel')}</a>
                    </div>
                </form>
            </section>
            HTML;
    }

    private function eventCard(
        TranslatorInterface $translator,
        string $locale,
        string $slug,
        AdminEvent $event,
        string $csrfToken,
        DateTimeImmutable $now,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key, array $parameters = []): string => htmlspecialchars(
            $translator->trans($key, $parameters),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $scheduledIsVisible = $event->publicationState === PublicationState::Scheduled
            && $event->publishAt !== null
            && $event->publishAt <= $now;
        $stateKey = $scheduledIsVisible
            ? 'admin.state_published'
            : 'admin.state_' . $event->publicationState->value;
        $date = $this->formatDate($event->details->timing->startsAt, $locale);
        $location = $event->details->location ?? $translator->trans('event.location_open');
        $editPath = '/pulse/manage/r/' . rawurlencode($slug) . '/events?edit=' . rawurlencode($event->publicId) . '&amp;lang=' . rawurlencode($locale);
        $actions = '<a href="' . $e($editPath) . '">' . $t('admin.edit') . '</a>';
        if ($event->publicationState === PublicationState::Draft
            || ($event->publicationState === PublicationState::Scheduled && !$scheduledIsVisible)
        ) {
            $actions .= $this->actionForm($translator, $locale, $slug, $event, 'publish', 'admin.publish', $csrfToken);
        }
        if (($event->publicationState === PublicationState::Published || $scheduledIsVisible)
            && $now < $event->details->timing->startsAt
        ) {
            $actions .= $event->rsvpClosedAt === null
                ? $this->actionForm(
                    $translator,
                    $locale,
                    $slug,
                    $event,
                    'close',
                    'admin.close_rsvp',
                    $csrfToken,
                    'admin.close_rsvp_confirm',
                )
                : $this->actionForm($translator, $locale, $slug, $event, 'open', 'admin.open_rsvp', $csrfToken);
        }
        if ($event->publicationState === PublicationState::Published || $scheduledIsVisible) {
            $actions .= $this->actionForm($translator, $locale, $slug, $event, 'cancel', 'admin.cancel_event', $csrfToken);
        }
        $actions .= $this->actionForm($translator, $locale, $slug, $event, 'duplicate', 'admin.duplicate', $csrfToken);
        $scheduled = $event->publicationState === PublicationState::Scheduled && !$scheduledIsVisible && $event->publishAt !== null
            ? '<span class="status">' . $t('admin.scheduled_for', ['%date%' => $this->formatDate($event->publishAt, $locale)]) . '</span>'
            : '';
        $closed = $event->rsvpClosedAt === null ? '' : '<span class="status">' . $t('admin.rsvp_closed') . '</span>';

        return <<<HTML
            <article class="event-card admin-card">
                <div class="event-statuses"><span class="status">{$t($stateKey)}</span>{$scheduled}{$closed}</div>
                <div class="event-card-head">
                    <div><h3>{$e($event->details->title)}</h3><p>{$e($date)}<br>{$e($location)}</p></div>
                    <div class="count"><strong>{$event->attendanceCount}</strong><span>{$t('admin.rsvp_count')}</span></div>
                </div>
                <div class="admin-actions">{$actions}</div>
            </article>
            HTML;
    }

    private function actionForm(
        TranslatorInterface $translator,
        string $locale,
        string $slug,
        AdminEvent $event,
        string $intent,
        string $labelKey,
        string $csrfToken,
        ?string $confirmKey = null,
    ): string {
        $action = '/pulse/manage/r/' . rawurlencode($slug) . '/events/' . rawurlencode($event->publicId);
        $confirmation = $confirmKey === null
            ? ''
            : ' data-confirm="' . htmlspecialchars(
                $translator->trans($confirmKey),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ) . '"';

        return sprintf(
            '<form method="post" action="%s"%s><input type="hidden" name="csrf" value="%s"><input type="hidden" name="lang" value="%s"><button class="link-button" name="intent" value="%s" type="submit">%s</button></form>',
            htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
            $confirmation,
            htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($intent, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($translator->trans($labelKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    private function inputDate(?DateTimeImmutable $date): string
    {
        return $date?->setTimezone(new DateTimeZone('Europe/Zurich'))->format('Y-m-d\TH:i') ?? '';
    }

    private function formatDate(DateTimeImmutable $date, string $locale): string
    {
        $regionalLocale = match ($locale) {
            'fr' => 'fr_CH',
            'it' => 'it_CH',
            'rm' => 'rm_CH',
            default => 'de_CH',
        };
        $formatter = new IntlDateFormatter(
            $regionalLocale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Europe/Zurich',
            IntlDateFormatter::GREGORIAN,
            'EEEE, d. MMMM y · HH:mm',
        );

        return $formatter->format($date) ?: $date->format('Y-m-d H:i');
    }
}
