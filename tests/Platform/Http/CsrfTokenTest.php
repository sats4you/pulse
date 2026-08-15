<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Http;

use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Http\CsrfToken;

final class CsrfTokenTest extends TestCase
{
    public function testTokenIsBoundToSessionAndRound(): void
    {
        $tokens = new CsrfToken(str_repeat('c', 32));
        $token = $tokens->issue('admin-session', 'bern-bitcoin');

        self::assertTrue($tokens->isValid($token, 'admin-session', 'bern-bitcoin'));
        self::assertFalse($tokens->isValid($token, 'other-session', 'bern-bitcoin'));
        self::assertFalse($tokens->isValid($token, 'admin-session', 'other-round'));
        self::assertFalse($tokens->isValid('', 'admin-session', 'bern-bitcoin'));
    }
}
