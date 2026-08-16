<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantPage;

final class ParticipantPageTest extends TestCase
{
    public function testGroupNameHasItsOwnProminentHeadingStyle(): void
    {
        $translations = dirname(__DIR__, 3) . '/resources/translations';
        $translator = TranslatorFactory::create('de', $translations);
        $html = (new ParticipantPage(new EventAccessPolicy()))->render(
            $translator,
            'de',
            'Bern Monthly Bitcoin Meetup',
            'bern-bitcoin',
            [],
            [],
            [
                'de' => 'Deutsch',
                'fr' => 'Français',
                'it' => 'Italiano',
                'rm' => 'Rumantsch Grischun',
            ],
            new DateTimeImmutable('2026-08-16T12:00:00+02:00'),
        );

        self::assertStringContainsString('<p class="eyebrow">pulse</p>', $html);
        self::assertStringContainsString('<p class="group-title">Bern Monthly Bitcoin Meetup</p>', $html);
        self::assertStringContainsString('<h1>Wer ist dabei?</h1>', $html);
    }
}
