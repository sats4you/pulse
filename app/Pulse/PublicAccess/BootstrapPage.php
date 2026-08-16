<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use Symfony\Contracts\Translation\TranslatorInterface;

final class BootstrapPage
{
    /** @param array<string, string> $languageNames */
    public function render(
        TranslatorInterface $translator,
        string $locale,
        string $publicSlug,
        string $exchangePath,
        string $eventsPath,
        array $languageNames,
    ): string {
        $t = static fn (string $key): string => htmlspecialchars($translator->trans($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $locale = htmlspecialchars($locale, ENT_QUOTES, 'UTF-8');
        $configAttribute = htmlspecialchars(json_encode([
            'slug' => $publicSlug,
            'exchange' => $exchangePath,
            'events' => $eventsPath,
            'locale' => $locale,
            'ready' => $translator->trans('access.ready'),
            'incomplete' => $translator->trans('access.incomplete'),
        ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $options = '';
        foreach ($languageNames as $code => $name) {
            $selected = $code === $locale ? ' selected' : '';
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
                $selected,
                htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return <<<HTML
            <!doctype html>
            <html lang="{$locale}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>{$t('access.heading')} · pulse</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/pulse.css?v=20260816-mobile1">
            </head>
            <body>
                <main class="shell" id="pulse-app" data-pulse-config="{$configAttribute}">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch">
                            <img class="brand-symbol" src="/assets/sats4you-mark.svg" alt="">
                            <span class="brand-word">sats<span class="brand-four">4</span>you<span class="brand-tld">.ch</span></span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language">{$options}</select>
                    </div>
                    <p class="eyebrow">{$t('access.eyebrow')}</p>
                    <h1>{$t('access.heading')}</h1>
                    <p class="lead">{$t('access.explanation')}</p>
                    <section class="access-card" aria-live="polite">
                        <div class="state-icon" aria-hidden="true">✓</div>
                        <div>
                            <strong id="state-title">{$t('access.progress')}</strong>
                            <p id="state-copy">{$t('privacy.short')}</p>
                        </div>
                        <a class="primary" id="continue" href="#" hidden>{$t('access.continue')}</a>
                        <details>
                            <summary>{$t('access.details')}</summary>
                            <p>{$t('privacy.short')}</p>
                            <p><a href="/pulse/privacy?lang={$locale}">{$t('privacy.link')}</a></p>
                        </details>
                    </section>
                    <p class="footnote">{$t('pilot.label')} · {$t('participant.no_account')}</p>
                </main>
                <script src="/assets/access-bootstrap.js" defer></script>
            </body>
            </html>
            HTML;
    }
}
