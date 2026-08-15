<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Security;

use InvalidArgumentException;

final readonly class SecretDigester
{
    public function __construct(private string $key)
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('The HMAC key must contain at least 32 bytes.');
        }
    }

    public function digest(string $secret): string
    {
        if ($secret === '') {
            throw new InvalidArgumentException('A secret cannot be empty.');
        }

        return hash_hmac('sha256', $secret, $this->key, true);
    }

    public function matches(string $secret, string $digest): bool
    {
        return hash_equals($digest, $this->digest($secret));
    }
}
