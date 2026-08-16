<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

final readonly class SameOriginGuard
{
    public function __construct(private string $expectedOrigin)
    {
    }

    public function allows(?string $origin, ?string $fetchSite = null): bool
    {
        if ($origin === null || $origin === '' || $origin === 'null') {
            return $fetchSite === 'same-origin';
        }

        $expected = $this->normaliseOrigin($this->expectedOrigin);
        $presented = $this->normaliseOrigin($origin);

        return $expected !== null && $presented !== null && hash_equals($expected, $presented);
    }

    private function normaliseOrigin(string $origin): ?string
    {
        $parts = parse_url(rtrim($origin, '/'));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return $scheme . '://' . $host . ($port !== null && !$defaultPort ? ':' . $port : '');
    }
}
