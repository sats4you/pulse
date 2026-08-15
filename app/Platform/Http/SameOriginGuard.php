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
        if ($origin !== null && $origin !== '') {
            return hash_equals(rtrim($this->expectedOrigin, '/'), rtrim($origin, '/'));
        }

        return $fetchSite === 'same-origin';
    }
}
