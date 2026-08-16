<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\PublicAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Tests\Support\InMemoryRoundAccessStore;

final class AccessExchangeTest extends TestCase
{
    public function testValidSecretCreatesRevocableParticipantSession(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $digester = new SecretDigester(str_repeat('h', 32));
        $store = new InMemoryRoundAccessStore();
        $store->participantDigest = $digester->digest('valid-secret');
        $exchange = new AccessExchange(
            $store,
            $digester,
            new AccessSessionCodec(str_repeat('s', 32)),
        );

        $cookie = $exchange->exchangeParticipant($store->slug, 'valid-secret', $now);

        self::assertNotNull($cookie);
        self::assertNotNull($exchange->validateParticipant($cookie, $store->slug, $now));

        ++$store->participantVersion;
        self::assertNull($exchange->validateParticipant($cookie, $store->slug, $now));
    }

    public function testAdministratorSessionExpiresAfterTwelveHoursAndCannotBeUsedAsParticipant(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $digester = new SecretDigester(str_repeat('h', 32));
        $store = new InMemoryRoundAccessStore();
        $store->administratorDigest = $digester->digest('admin-secret');
        $exchange = new AccessExchange($store, $digester, new AccessSessionCodec(str_repeat('s', 32)));

        $cookie = $exchange->exchangeAdministrator($store->slug, 'admin-secret', $now);

        self::assertNotNull($cookie);
        self::assertNotNull($exchange->validateAdministrator($cookie, $store->slug, $now->modify('+11 hours')));
        self::assertNull($exchange->validateParticipant($cookie, $store->slug, $now));
        self::assertNull($exchange->validateAdministrator($cookie, $store->slug, $now->modify('+12 hours')));
    }

    public function testWrongSecretIsRejectedWithoutSession(): void
    {
        $digester = new SecretDigester(str_repeat('h', 32));
        $store = new InMemoryRoundAccessStore();
        $store->participantDigest = $digester->digest('valid-secret');
        $exchange = new AccessExchange(
            $store,
            $digester,
            new AccessSessionCodec(str_repeat('s', 32)),
        );

        self::assertNull($exchange->exchangeParticipant(
            $store->slug,
            'wrong-secret',
            new DateTimeImmutable('2026-08-15T12:00:00Z'),
        ));
    }

    public function testRecoverySessionExpiresAfterTenMinutesAndIsVersionRevocable(): void
    {
        $now = new DateTimeImmutable('2026-08-15T12:00:00Z');
        $digester = new SecretDigester(str_repeat('h', 32));
        $store = new InMemoryRoundAccessStore();
        $store->recoveryDigest = $digester->digest('offline-recovery-secret');
        $exchange = new AccessExchange($store, $digester, new AccessSessionCodec(str_repeat('s', 32)));

        $cookie = $exchange->exchangeRecovery($store->slug, 'offline-recovery-secret', $now);

        self::assertNotNull($cookie);
        self::assertNotNull($exchange->validateRecovery($cookie, $store->slug, $now->modify('+9 minutes')));
        self::assertNull($exchange->validateAdministrator($cookie, $store->slug, $now));
        self::assertNull($exchange->validateRecovery($cookie, $store->slug, $now->modify('+10 minutes')));

        $cookie = $exchange->exchangeRecovery($store->slug, 'offline-recovery-secret', $now);
        ++$store->recoveryVersion;
        self::assertNull($exchange->validateRecovery((string) $cookie, $store->slug, $now));
    }
}
