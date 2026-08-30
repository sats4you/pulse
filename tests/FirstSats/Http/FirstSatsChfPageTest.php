<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Http\FirstSatsPage;
use Sats4you\Pulse\FirstSats\Pricing\ChfQuote;
use Sats4you\Pulse\FirstSats\Pricing\ChfQuoteProvider;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;

final class FirstSatsChfPageTest extends TestCase
{
    public function testFormShowsCalculatedPresetsReferenceAndMethodLink(): void
    {
        $quote = ChfQuote::fromDecimalStrings('89999', '90001', new DateTimeImmutable('2026-08-24 12:34:00', new DateTimeZone('Europe/Zurich')));
        $page = new FirstSatsPage(new FixedQuoteProvider($quote));
        $t = TranslatorFactory::create('de', dirname(__DIR__, 3) . '/resources/first-sats');
        $html = $page->form($t, 'de', ['de' => 'Deutsch'], 'csrf', ['sats' => '5000']);

        self::assertStringContainsString("1'000 Sats", $html);
        self::assertStringContainsString('ca. CHF 0.90', $html);
        self::assertStringContainsString('ca. CHF 4.50', $html);
        self::assertStringContainsString('ca. CHF 9.00', $html);
        self::assertStringContainsString('Referenzkurs CHF 90&#039;000.00 pro BTC', $html);
        self::assertStringContainsString('24.08.2026 12:34', $html);
        self::assertStringContainsString('/erste-sats/chf-methodology?lang=de', $html);
        self::assertStringContainsString('data-quote-sum="1800000000"', $html);
    }

    public function testUnavailableQuoteNeverInventsAReplacementValue(): void
    {
        $page = new FirstSatsPage(new FixedQuoteProvider(null));
        $t = TranslatorFactory::create('de', dirname(__DIR__, 3) . '/resources/first-sats');
        $html = $page->form($t, 'de', ['de' => 'Deutsch'], 'csrf', ['sats' => '5000']);

        self::assertStringContainsString('CHF-Wert vorübergehend nicht verfügbar', $html);
        self::assertStringNotContainsString('90\'000.00 pro BTC', $html);
    }
}

final readonly class FixedQuoteProvider implements ChfQuoteProvider
{
    public function __construct(private ?ChfQuote $quote)
    {
    }

    public function current(DateTimeImmutable $now): ?ChfQuote
    {
        return $this->quote;
    }
}
