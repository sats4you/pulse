<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class AccessSessionCodec
{
    public function __construct(private string $signingKey)
    {
        if (strlen($signingKey) < 32) {
            throw new InvalidArgumentException('The session signing key must contain at least 32 bytes.');
        }
    }

    public function encode(AccessSession $session): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'rid' => $session->roundId,
            'role' => $session->role->value,
            'ver' => $session->accessVersion,
            'exp' => $session->expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $payload, $this->signingKey, true));

        return $payload . '.' . $signature;
    }

    public function decode(string $cookie, DateTimeImmutable $now): ?AccessSession
    {
        $parts = explode('.', $cookie);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->signingKey, true);
        $provided = self::base64UrlDecode($signature);
        if ($provided === null || !hash_equals($expected, $provided)) {
            return null;
        }

        $json = self::base64UrlDecode($payload);
        if ($json === null) {
            return null;
        }

        try {
            $claims = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($claims)
                || !is_string($claims['rid'] ?? null)
                || !is_string($claims['role'] ?? null)
                || !is_int($claims['ver'] ?? null)
                || !is_int($claims['exp'] ?? null)
            ) {
                return null;
            }

            $role = AccessRole::tryFrom($claims['role']);
            $expiresAt = (new DateTimeImmutable())->setTimestamp($claims['exp']);
            if ($role === null || $expiresAt <= $now) {
                return null;
            }

            return new AccessSession($claims['rid'], $role, $claims['ver'], $expiresAt);
        } catch (JsonException) {
            return null;
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

        return $decoded === false ? null : $decoded;
    }
}
