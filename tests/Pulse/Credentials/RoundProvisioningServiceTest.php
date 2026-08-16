<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Credentials;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Credentials\RoundProvisioningService;
use Sats4you\Pulse\Pulse\Credentials\RoundProvisioningStore;

final class RoundProvisioningServiceTest extends TestCase
{
    public function testProvisioningStoresOnlyDigestsAndReturnsSecretsOnce(): void
    {
        $store = new class implements RoundProvisioningStore {
            /** @var array<string, mixed> */
            public array $row = [];

            public function createRound(
                string $roundId,
                string $publicSlug,
                string $name,
                string $timezone,
                string $participantDigest,
                string $administratorDigest,
                string $recoveryDigest,
                DateTimeImmutable $createdAt,
            ): void {
                $this->row = get_defined_vars();
            }
        };
        $digester = new SecretDigester(str_repeat('h', 32));
        $service = new RoundProvisioningService($store, new SecretGenerator(), $digester);

        $result = $service->provision(
            'bern-bitcoin',
            'Bern Monthly Bitcoin Meetup',
            'Europe/Zurich',
            new DateTimeImmutable('2026-08-16T12:00:00Z'),
        );

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $store->row['roundId']);
        self::assertTrue($digester->matches($result->participantSecret, $store->row['participantDigest']));
        self::assertTrue($digester->matches($result->administratorSecret, $store->row['administratorDigest']));
        self::assertTrue($digester->matches($result->recoverySecret, $store->row['recoveryDigest']));
        self::assertFalse(in_array($result->participantSecret, $store->row, true));
        self::assertFalse(in_array($result->administratorSecret, $store->row, true));
        self::assertFalse(in_array($result->recoverySecret, $store->row, true));
    }
}
