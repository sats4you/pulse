<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

use DateTimeImmutable;
use DateTimeZone;

final class CookieHeader
{
    public static function create(
        string $name,
        string $value,
        string $path,
        DateTimeImmutable $expiresAt,
        bool $secure,
    ): string {
        $expiresAtUtc = $expiresAt->setTimezone(new DateTimeZone('GMT'));
        $parts = [
            rawurlencode($name) . '=' . rawurlencode($value),
            'Path=' . $path,
            'Expires=' . $expiresAtUtc->format('D, d M Y H:i:s') . ' GMT',
            'Max-Age=' . max(0, $expiresAt->getTimestamp() - time()),
            'HttpOnly',
            'SameSite=Strict',
        ];
        if ($secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public static function expire(string $name, string $path, bool $secure): string
    {
        return self::create($name, '', $path, new DateTimeImmutable('@1'), $secure);
    }
}
