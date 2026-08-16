<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use Symfony\Contracts\Translation\TranslatorInterface;

final class RecoveryBootstrapPage
{
    /** @param array<string, string> $languageNames */
    public function render(
        TranslatorInterface $translator,
        string $locale,
        string $publicSlug,
        string $exchangePath,
        string $destinationPath,
        array $languageNames,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key): string => $e($translator->trans($key));
        $locale = in_array($locale, ['de', 'fr', 'it', 'rm'], true) ? $locale : 'de';
        $config = $e(json_encode([
            'slug' => $publicSlug,
            'exchange' => $exchangePath,
            'events' => $destinationPath,
            'locale' => $locale,
            'ready' => $translator->trans('recovery.access_ready'),
            'incomplete' => $translator->trans('access.incomplete'),
        ], JSON_THROW_ON_ERROR));
        $options = '';
        foreach ($languageNames as $code => $name) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $e($code),
                $code === $locale ? ' selected' : '',
                $e($name),
            );
        }

        return <<<HTML
            <!doctype html>
            <html lang="{$e($locale)}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>{$t('recovery.access_heading')} · pulse</title>
                <link rel="stylesheet" href="/assets/pulse.css">
            </head>
            <body>
                <main class="shell" id="pulse-app" data-pulse-config="{$config}">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch">
                            <span class="brandmark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                            <span>sats4you.ch</span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language">{$options}</select>
                    </div>
                    <p class="eyebrow">{$t('recovery.access_eyebrow')}</p>
                    <h1>{$t('recovery.access_heading')}</h1>
                    <p class="lead">{$t('recovery.access_explanation')}</p>
                    <section class="access-card" aria-live="polite">
                        <div class="state-icon" aria-hidden="true">✓</div>
                        <div>
                            <strong id="state-title">{$t('access.progress')}</strong>
                            <p id="state-copy">{$t('recovery.access_privacy')}</p>
                        </div>
                        <a class="primary" id="continue" href="#" hidden>{$t('recovery.access_continue')}</a>
                    </section>
                    <p class="footnote">{$t('pilot.label')} · {$t('recovery.offline_notice')}</p>
                </main>
                <script src="/assets/access-bootstrap.js" defer></script>
            </body>
            </html>
            HTML;
    }
}
