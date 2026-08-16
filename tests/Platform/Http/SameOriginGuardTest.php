<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;

final class SameOriginGuardTest extends TestCase
{
    public static function cases(): iterable
    {
        yield 'exact origin' => ['https://sats4you.ch', null, true];
        yield 'trailing slash' => ['https://sats4you.ch/', null, true];
        yield 'default HTTPS port' => ['https://sats4you.ch:443', null, true];
        yield 'host and scheme are case insensitive' => ['HTTPS://SATS4YOU.CH', null, true];
        yield 'same-origin fetch metadata' => [null, 'same-origin', true];
        yield 'opaque origin with same-origin metadata' => ['null', 'same-origin', true];
        yield 'missing' => [null, null, false];
        yield 'opaque origin without metadata' => ['null', null, false];
        yield 'same-site is insufficient' => [null, 'same-site', false];
        yield 'other subdomain' => ['https://evil.sats4you.ch', 'same-site', false];
        yield 'different scheme' => ['http://sats4you.ch', 'same-origin', false];
        yield 'non-default port' => ['https://sats4you.ch:8443', 'same-origin', false];
    }

    #[DataProvider('cases')]
    public function testOriginMustMatch(?string $origin, ?string $fetchSite, bool $expected): void
    {
        self::assertSame($expected, (new SameOriginGuard('https://sats4you.ch'))->allows($origin, $fetchSite));
    }
}
