<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $robots = in_array($request->getMethod(), ['GET', 'HEAD'], true) && in_array($path, [
            '/erste-sats',
            '/erste-sats/',
            '/erste-sats/faq',
            '/erste-sats/terms',
            '/erste-sats/privacy',
            '/erste-sats/chf-methodology',
        ], true) ? 'index, follow' : 'noindex, nofollow, noarchive';

        return $handler->handle($request)
            ->withHeader('Content-Security-Policy', "default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'")
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->withHeader('X-Robots-Tag', $robots)
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
