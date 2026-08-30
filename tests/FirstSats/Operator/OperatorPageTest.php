<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Operator;

use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Operator\DashboardData;
use Sats4you\Pulse\FirstSats\Operator\OperatorPage;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;

final class OperatorPageTest extends TestCase
{
    public function testBootstrapProvidesAllMessagesRequiredBySharedAccessScript(): void
    {
        $translator = TranslatorFactory::create('fr', dirname(__DIR__, 3) . '/resources/first-sats');
        $html = (new OperatorPage())->bootstrap(
            $translator,
            'fr',
            ['fr' => 'Français'],
        );
        preg_match('/data-config="([^"]+)"/', $html, $match);
        $config = json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('L’accès est prêt.', $config['ready'] ?? null);
        self::assertStringStartsWith('Ce lien d’invitation est incomplet.', $config['incomplete'] ?? '');
        self::assertStringStartsWith('L’accès n’a pas pu être ouvert.', $config['denied'] ?? '');
    }

    public function testSmallCellsAreSuppressedAndNoIndividualDataIsRendered(): void
    {
        $data = new DashboardData(
            ['created' => 10, 'opened' => 9, 'started' => 8, 'completed' => 7, 'payment_requested' => 6, 'sent_confirmed' => 5, 'received_confirmed' => 4, 'participant_ended' => 3],
            ['wos' => 9, 'phoenix' => 2, 'other' => 1],
            ['yes' => 4, 'no' => 3, 'unsure' => 2, 'skipped' => 1],
            '2026-08-23-v1',
        );
        $translator = TranslatorFactory::create('de', dirname(__DIR__, 3) . '/resources/first-sats');
        $html = (new OperatorPage())->dashboard($translator, 'de', ['de' => 'Deutsch'], $data, 'https://sats4you.ch');

        self::assertStringContainsString('<strong>10</strong>', $html);
        self::assertStringContainsString('Noch zu wenige Antworten', $html);
        self::assertStringNotContainsString('<strong>9</strong>', $html);
        self::assertStringNotContainsString('lnbc', $html);
        self::assertStringNotContainsString('Einladung 1', $html);
        self::assertStringContainsString('noindex,nofollow', $html);
    }
}
