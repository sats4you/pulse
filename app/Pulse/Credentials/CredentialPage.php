<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use Symfony\Contracts\Translation\TranslatorInterface;

final class CredentialPage
{
    /** @param array<string, string> $languageNames */
    public function renderRecoveryConfirmation(
        TranslatorInterface $translator,
        string $locale,
        string $slug,
        string $csrfToken,
        array $languageNames,
    ): string {
        $form = sprintf(
            '<form class="credential-form" method="post" action="/pulse/recover/r/%s/rotate"><input type="hidden" name="csrf" value="%s"><input type="hidden" name="lang" value="%s"><label class="confirmation"><input type="checkbox" name="confirm" value="rotate" required> <span>%s</span></label><button class="primary-button" type="submit">%s</button></form>',
            rawurlencode($slug),
            self::e($csrfToken),
            self::e($locale),
            self::t($translator, 'recovery.confirm_checkbox'),
            self::t($translator, 'recovery.confirm_button'),
        );

        return $this->layout(
            $translator,
            $locale,
            'recovery.confirm_heading',
            'recovery.confirm_eyebrow',
            'recovery.confirm_intro',
            '<p class="warning">' . self::t($translator, 'recovery.confirm_warning') . '</p>' . $form,
            $languageNames,
            '/pulse/recover/r/' . rawurlencode($slug) . '/confirm',
        );
    }

    /** @param array<string, string> $languageNames */
    public function renderRecoveryResult(
        TranslatorInterface $translator,
        string $locale,
        string $administratorLink,
        string $recoveryCode,
        string $recoveryLink,
        array $languageNames,
    ): string {
        $content = '<p class="warning">' . self::t($translator, 'recovery.result_once') . '</p>'
            . $this->secretBlock($translator, 'recovery.result_admin_link', $administratorLink, true)
            . $this->secretBlock($translator, 'recovery.result_code', $recoveryCode, false)
            . $this->secretBlock($translator, 'recovery.result_link', $recoveryLink, true)
            . '<p class="lead">' . self::t($translator, 'recovery.result_old_invalid') . '</p>';

        return $this->layout(
            $translator,
            $locale,
            'recovery.result_heading',
            'recovery.result_eyebrow',
            'recovery.result_intro',
            $content,
            $languageNames,
            '',
        );
    }

    /** @param array<string, string> $languageNames */
    public function renderParticipantResult(
        TranslatorInterface $translator,
        string $locale,
        string $participantLink,
        string $adminEventsPath,
        array $languageNames,
    ): string {
        $content = '<p class="warning">' . self::t($translator, 'credentials.participant_once') . '</p>'
            . $this->secretBlock($translator, 'credentials.participant_link', $participantLink, true)
            . '<p class="lead">' . self::t($translator, 'credentials.participant_old_invalid') . '</p>'
            . '<p><a class="primary inline-primary" href="' . self::e($adminEventsPath) . '">' . self::t($translator, 'credentials.back_admin') . '</a></p>';

        return $this->layout(
            $translator,
            $locale,
            'credentials.participant_result_heading',
            'credentials.eyebrow',
            'credentials.participant_result_intro',
            $content,
            $languageNames,
            '',
        );
    }

    private function secretBlock(
        TranslatorInterface $translator,
        string $labelKey,
        string $value,
        bool $link,
    ): string {
        $visible = $link
            ? '<a href="' . self::e($value) . '">' . self::e($value) . '</a>'
            : '<code>' . self::e($value) . '</code>';

        return '<section class="secret-output"><h2>' . self::t($translator, $labelKey) . '</h2><div>' . $visible . '</div></section>';
    }

    /** @param array<string, string> $languageNames */
    private function layout(
        TranslatorInterface $translator,
        string $locale,
        string $headingKey,
        string $eyebrowKey,
        string $introKey,
        string $content,
        array $languageNames,
        string $languagePath,
    ): string {
        $locale = in_array($locale, ['de', 'fr', 'it', 'rm'], true) ? $locale : 'de';
        $options = '';
        foreach ($languageNames as $code => $name) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                self::e($code),
                $code === $locale ? ' selected' : '',
                self::e($name),
            );
        }
        $language = $languagePath === '' ? '' : <<<HTML
            <div class="language">
                <label for="language">%s</label>
                <select id="language" data-current-path="%s">{$options}</select>
            </div>
            HTML;
        if ($language !== '') {
            $language = sprintf(
                $language,
                self::t($translator, 'language.select'),
                self::e($languagePath),
            );
        }
        $script = $languagePath === '' ? '' : '<script src="/assets/participant-page.js" defer></script>';
        $escapedLocale = self::e($locale);
        $heading = self::t($translator, $headingKey);
        $eyebrow = self::t($translator, $eyebrowKey);
        $intro = self::t($translator, $introKey);
        $pilot = self::t($translator, 'pilot.label');

        return <<<HTML
            <!doctype html>
            <html lang="{$escapedLocale}">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="noindex,nofollow,noarchive">
                <title>{$heading} · pulse</title>
                <link rel="icon" href="/assets/sats4you-favicon.svg" type="image/svg+xml">
                <link rel="stylesheet" href="/assets/pulse.css?v=20260816-ci1">
            </head>
            <body>
                <main class="shell credential-shell">
                    <header class="brandbar">
                        <a class="brand" href="/pulse" aria-label="sats4you.ch"><img class="brand-symbol" src="/assets/sats4you-mark.svg" alt=""><span class="brand-word">sats<span class="brand-four">4</span>you<span class="brand-tld">.ch</span></span></a>
                        <span class="pilot">{$pilot}</span>
                    </header>
                    {$language}
                    <p class="eyebrow">{$eyebrow}</p>
                    <h1>{$heading}</h1>
                    <p class="lead">{$intro}</p>
                    {$content}
                </main>
                {$script}
            </body>
            </html>
            HTML;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function t(TranslatorInterface $translator, string $key): string
    {
        return self::e($translator->trans($key));
    }

}
