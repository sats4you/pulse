<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Security;

use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final class SecretTest extends TestCase
{
    public function testGeneratedSecretContainsAtLeast256BitsAndIsUrlSafe(): void
    {
        $secret = (new SecretGenerator())->generate();

        self::assertSame(43, strlen($secret));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $secret);
    }

    public function testDigestMatchesOnlyOriginalSecret(): void
    {
        $digester = new SecretDigester(str_repeat('k', 32));
        $digest = $digester->digest('correct-secret');

        self::assertTrue($digester->matches('correct-secret', $digest));
        self::assertFalse($digester->matches('wrong-secret', $digest));
        self::assertSame(32, strlen($digest));
    }
}
