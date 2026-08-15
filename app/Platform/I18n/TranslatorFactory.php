<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\I18n;

use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Translator;

final class TranslatorFactory
{
    public const SUPPORTED_LOCALES = ['de', 'fr', 'it', 'rm'];

    public static function create(string $locale, string $translationDirectory): Translator
    {
        $effectiveLocale = in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'de';
        $translator = new Translator($effectiveLocale);
        $translator->setFallbackLocales(['de']);
        $translator->addLoader('json', new JsonFileLoader());

        foreach (self::SUPPORTED_LOCALES as $supportedLocale) {
            $translator->addResource(
                'json',
                $translationDirectory . '/messages.' . $supportedLocale . '.json',
                $supportedLocale,
            );
        }

        return $translator;
    }
}
