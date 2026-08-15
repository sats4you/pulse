<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

use InvalidArgumentException;

final readonly class CsrfToken
{
    public function __construct(private string $key)
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('The CSRF key must contain at least 32 bytes.');
        }
    }

    public function issue(string $sessionCookie, string $publicSlug): string
    {
        return rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            "pulse-admin\0" . $publicSlug . "\0" . $sessionCookie,
            $this->key,
            true,
        )), '+/', '-_'), '=');
    }

    public function isValid(string $token, string $sessionCookie, string $publicSlug): bool
    {
        return $token !== '' && hash_equals($this->issue($sessionCookie, $publicSlug), $token);
    }
}
