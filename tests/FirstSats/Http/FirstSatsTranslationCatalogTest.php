<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Http;

use PHPUnit\Framework\TestCase;

final class FirstSatsTranslationCatalogTest extends TestCase
{
    public function testAllFourCataloguesContainTheSameKeysAndNoEmptyValues(): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/first-sats';
        $catalogues = [];
        foreach (['de', 'fr', 'it', 'rm'] as $locale) {
            $decoded = json_decode((string) file_get_contents($directory . '/messages.' . $locale . '.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertNotContains('', array_values($decoded), 'Empty translation in ' . $locale);
            $keys = array_keys($decoded); sort($keys); $catalogues[$locale] = $keys;
        }
        foreach (['fr', 'it', 'rm'] as $locale) {
            self::assertSame($catalogues['de'], $catalogues[$locale], 'Translation keys differ for ' . $locale);
        }
    }
}
