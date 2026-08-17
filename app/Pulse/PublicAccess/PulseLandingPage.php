<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use Sats4you\Pulse\Pulse\Shared\ProjectLinks;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PulseLandingPage
{
    /** @param array<string, string> $languageNames */
    public function render(TranslatorInterface $translator, string $locale, array $languageNames): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key): string => htmlspecialchars($translator->trans($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $options = '';
        foreach ($languageNames as $code => $name) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $e($code),
                $code === $locale ? ' selected' : '',
                $e($name),
            );
        }
        $projectLinks = ProjectLinks::render($translator, $locale);

        return <<<HTML
            <!doctype html>
            <html lang="{$e($locale)}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>pulse · sats4you.ch</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/pulse.css?v=20260816-mobile1">
            </head>
            <body>
                <main class="shell landing-shell">
                    <header class="brandbar">
                        <a class="brand" href="/" aria-label="sats4you.ch">
                            <img class="brand-symbol" src="/assets/sats4you-mark.svg" alt="">
                            <span class="brand-word">sats<span class="brand-four">4</span>you<span class="brand-tld">.ch</span></span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language" data-current-path="/pulse">{$options}</select>
                    </div>
                    <p class="eyebrow">pulse</p>
                    <h1>{$t('landing.heading')}</h1>
                    <p class="lead">{$t('landing.intro')}</p>
                    <section class="access-card landing-card">
                        <div class="state-icon" aria-hidden="true">↗</div>
                        <div><strong>{$t('landing.access_title')}</strong><p>{$t('landing.access_text')}</p></div>
                    </section>
                    <p><a class="privacy-link" href="/pulse/privacy?lang={$e($locale)}">{$t('privacy.link')}</a></p>
                    <p class="footnote">{$t('landing.pilot_scope')}</p>
                    {$projectLinks}
                </main>
                <script src="/assets/participant-page.js" defer></script>
            </body>
            </html>
            HTML;
    }
}
