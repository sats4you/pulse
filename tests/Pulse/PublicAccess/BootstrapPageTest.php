<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;

use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;

final class BootstrapPageTest extends TestCase
{
    public function testPageUsesTranslationsAndFullLanguageNames(): void
    {
        $translations = dirname(__DIR__, 3) . '/resources/translations';
        $translator = TranslatorFactory::create('fr', $translations);
        $html = (new BootstrapPage())->render(
            $translator,
            'fr',
            'bern-bitcoin',
            '/exchange',
            '/events',
            [
                'de' => 'Deutsch',
                'fr' => 'Français',
                'it' => 'Italiano',
                'rm' => 'Rumantsch Grischun',
            ],
        );

        self::assertStringContainsString('<html lang="fr">', $html);
        self::assertStringContainsString('Ouverture de l’accès participant', $html);
        self::assertStringContainsString('Rumantsch Grischun', $html);
        self::assertStringNotContainsString('test-participant-secret', $html);
    }
}
