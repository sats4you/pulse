<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSession;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final class AccessSessionCodecTest extends TestCase
{
    private AccessSessionCodec $codec;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->codec = new AccessSessionCodec(str_repeat('s', 32));
        $this->now = new DateTimeImmutable('2026-08-15T12:00:00Z');
    }

    public function testRoundTripPreservesMinimalClaims(): void
    {
        $session = new AccessSession(
            '0123456789abcdef0123456789abcdef',
            AccessRole::Participant,
            3,
            $this->now->modify('+1 day'),
        );

        $decoded = $this->codec->decode($this->codec->encode($session), $this->now);

        self::assertNotNull($decoded);
        self::assertSame($session->roundId, $decoded->roundId);
        self::assertSame(AccessRole::Participant, $decoded->role);
        self::assertSame(3, $decoded->accessVersion);
    }

    public function testTamperedCookieIsRejected(): void
    {
        $cookie = $this->codec->encode(new AccessSession(
            '0123456789abcdef0123456789abcdef',
            AccessRole::Participant,
            1,
            $this->now->modify('+1 day'),
        ));

        self::assertNull($this->codec->decode('x' . $cookie, $this->now));
    }

    public function testExpiredCookieIsRejected(): void
    {
        $cookie = $this->codec->encode(new AccessSession(
            '0123456789abcdef0123456789abcdef',
            AccessRole::Participant,
            1,
            $this->now,
        ));

        self::assertNull($this->codec->decode($cookie, $this->now));
    }
}
