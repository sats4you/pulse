<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

use Symfony\Contracts\Translation\TranslatorInterface;

final class PlatformLandingPage
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

        return <<<HTML
            <!doctype html>
            <html lang="{$e($locale)}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="index,follow">
                <title>sats4you.ch</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/platform.css?v=20260906-1">
            </head>
            <body>
                <main>
                    <header>
                        <a class="brand" href="/?lang={$e($locale)}"><img src="/assets/sats4you-wordmark.svg" alt="sats4you.ch"></a>
                        <label class="language"><span class="visually-hidden">{$t('language.select')}</span><select id="language" data-current-path="/">{$options}</select></label>
                    </header>
                    <section class="hero">
                        <p class="eyebrow">{$t('platform.eyebrow')}</p>
                        <h1>{$t('platform.heading')}</h1>
                        <p class="lead">{$t('platform.intro')}</p>
                    </section>
                    <section class="products" aria-label="{$t('platform.products_label')}">
                        <article class="product product-first-sats">
                            <img src="/assets/sats4you-mark-mono-light.svg" alt="">
                            <div><p class="product-name">{$t('platform.first_sats_title')}</p><p>{$t('platform.first_sats_text')}</p></div>
                            <a href="/erste-sats?lang={$e($locale)}">{$t('platform.first_sats_action')} <span aria-hidden="true">→</span></a>
                        </article>
                        <article class="product product-pulse">
                            <span class="pulse-mark" aria-hidden="true">●</span>
                            <div><p class="product-name">pulse</p><p>{$t('platform.pulse_text')}</p></div>
                            <a href="/pulse?lang={$e($locale)}">{$t('platform.pulse_action')} <span aria-hidden="true">→</span></a>
                        </article>
                    </section>
                    <footer>{$t('platform.footer')}</footer>
                </main>
                <script src="/assets/participant-page.js" defer></script>
            </body>
            </html>
            HTML;
    }
}
