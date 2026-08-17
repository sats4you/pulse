<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Privacy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\Privacy\PrivacyPage;

final class PrivacyPageTest extends TestCase
{
    public static function locales(): iterable
    {
        yield ['de', 'Was pulse über dich weiss'];
        yield ['fr', 'Ce que pulse sait de toi'];
        yield ['it', 'Cosa sa pulse di te'];
        yield ['rm', 'Tge che pulse sa da tai'];
    }

    #[DataProvider('locales')]
    public function testExplanationIsCompleteAndAvailableInEveryPilotLanguage(string $locale, string $heading): void
    {
        $directory = dirname(__DIR__, 3) . '/resources/translations';
        $html = (new PrivacyPage())->render(
            TranslatorFactory::create($locale, $directory),
            $locale,
            ['de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano', 'rm' => 'Rumantsch Grischun'],
        );

        self::assertStringContainsString($heading, $html);
        self::assertStringContainsString('Andreas Kuoni', $html);
        self::assertStringContainsString('security@sats4you.ch', $html);
        self::assertStringContainsString('https://github.com/sats4you/pulse', $html);
        self::assertStringContainsString('Rumantsch Grischun', $html);
        self::assertStringContainsString('lima-city', $html);
        self::assertStringContainsString('90', $html);
        self::assertStringContainsString('noindex,nofollow,noarchive', $html);
    }
}
