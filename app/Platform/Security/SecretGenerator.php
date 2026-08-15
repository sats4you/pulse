<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Security;

final class SecretGenerator
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
