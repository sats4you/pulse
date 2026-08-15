<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\I18n;

use PHPUnit\Framework\TestCase;

final class TranslationCatalogTest extends TestCase
{
    public function testAllFourLanguagesContainTheSameNonEmptyKeys(): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/translations';
        $catalogues = [];
        foreach (['de', 'fr', 'it', 'rm'] as $locale) {
            $json = file_get_contents($directory . '/messages.' . $locale . '.json');
            self::assertNotFalse($json);
            $catalogue = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($catalogue);
            foreach ($catalogue as $key => $value) {
                self::assertIsString($key);
                self::assertIsString($value);
                self::assertNotSame('', trim($value), $locale . ':' . $key);
            }
            ksort($catalogue);
            $catalogues[$locale] = $catalogue;
        }

        $expectedKeys = array_keys($catalogues['de']);
        foreach ($catalogues as $locale => $catalogue) {
            self::assertSame($expectedKeys, array_keys($catalogue), 'Translation keys differ for ' . $locale);
        }
    }
}
