<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Privacy;

use Symfony\Contracts\Translation\TranslatorInterface;

final class PrivacyPage
{
    /** @param array<string, string> $languageNames */
    public function render(
        TranslatorInterface $translator,
        string $locale,
        array $languageNames,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn (string $key): string => htmlspecialchars(
            $translator->trans($key),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
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
                <title>{$t('privacy.heading')} · pulse</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/pulse.css?v=20260816-ci1">
            </head>
            <body>
                <main class="shell prose-shell">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch">
                            <img class="brand-symbol" src="/assets/sats4you-mark.svg" alt="">
                            <span class="brand-word">sats<span class="brand-four">4</span>you<span class="brand-tld">.ch</span></span>
                        </a>
                        <span class="pilot">{$t('pilot.label')}</span>
                    </header>
                    <div class="language">
                        <label for="language">{$t('language.select')}</label>
                        <select id="language" data-current-path="/pulse/privacy">{$options}</select>
                    </div>
                    <p class="eyebrow">pulse · {$t('privacy.eyebrow')}</p>
                    <h1>{$t('privacy.heading')}</h1>
                    <p class="lead">{$t('privacy.intro')}</p>
                    <p class="document-state">{$t('privacy.document_state')}</p>

                    <section>
                        <h2>{$t('privacy.promise_heading')}</h2>
                        <p>{$t('privacy.promise')}</p>
                    </section>
                    <section>
                        <h2>{$t('privacy.flow_heading')}</h2>
                        <div class="privacy-table" role="table" aria-label="{$t('privacy.flow_heading')}">
                            <div role="row"><strong role="rowheader">{$t('privacy.flow_others_title')}</strong><span role="cell">{$t('privacy.flow_others_text')}</span></div>
                            <div role="row"><strong role="rowheader">{$t('privacy.flow_admin_title')}</strong><span role="cell">{$t('privacy.flow_admin_text')}</span></div>
                            <div role="row"><strong role="rowheader">{$t('privacy.flow_browser_title')}</strong><span role="cell">{$t('privacy.flow_browser_text')}</span></div>
                            <div role="row"><strong role="rowheader">{$t('privacy.flow_server_title')}</strong><span role="cell">{$t('privacy.flow_server_text')}</span></div>
                            <div role="row"><strong role="rowheader">{$t('privacy.flow_host_title')}</strong><span role="cell">{$t('privacy.flow_host_text')}</span></div>
                        </div>
                    </section>
                    <section>
                        <h2>{$t('privacy.withdraw_heading')}</h2>
                        <p>{$t('privacy.withdraw_text')}</p>
                    </section>
                    <section>
                        <h2>{$t('privacy.retention_heading')}</h2>
                        <p>{$t('privacy.retention_text')}</p>
                        <p class="warning">{$t('privacy.hosting_gate')}</p>
                    </section>
                    <section>
                        <h2>{$t('privacy.limits_heading')}</h2>
                        <p>{$t('privacy.limits_text')}</p>
                    </section>
                    <section>
                        <h2>{$t('privacy.review_heading')}</h2>
                        <p>{$t('privacy.review_text')}</p>
                        <p><a href="mailto:security@sats4you.ch">security@sats4you.ch</a></p>
                    </section>
                    <p class="footnote">{$t('privacy.version')}</p>
                </main>
                <script src="/assets/participant-page.js" defer></script>
            </body>
            </html>
            HTML;
    }
}
