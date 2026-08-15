<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Http;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Http\CookieHeader;

final class CookieHeaderTest extends TestCase
{
    public function testExpiryIsAlwaysRenderedAsUtc(): void
    {
        $header = CookieHeader::create(
            'pulse_access',
            'secret',
            '/pulse/r/bern-bitcoin',
            new DateTimeImmutable('2026-08-16T12:00:00+02:00'),
            true,
        );

        self::assertStringContainsString('Expires=Sun, 16 Aug 2026 10:00:00 GMT', $header);
        self::assertStringContainsString('Path=/pulse/r/bern-bitcoin', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('Secure', $header);
    }

    public function testExpiredCookieHasZeroMaxAge(): void
    {
        $header = CookieHeader::expire('pulse_rsvp_event', '/pulse/r/bern-bitcoin', false);

        self::assertStringContainsString('pulse_rsvp_event=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringNotContainsString('Secure', $header);
    }
}
