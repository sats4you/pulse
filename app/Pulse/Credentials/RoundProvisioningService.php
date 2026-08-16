<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final readonly class RoundProvisioningService
{
    public function __construct(
        private RoundProvisioningStore $store,
        private SecretGenerator $generator,
        private SecretDigester $digester,
    ) {
    }

    public function provision(
        string $publicSlug,
        string $name,
        string $timezone,
        DateTimeImmutable $now,
    ): InitialCredentialSet {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $publicSlug) !== 1 || strlen($publicSlug) > 100) {
            throw new InvalidArgumentException('Invalid public slug.');
        }
        if ($name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Invalid group name.');
        }
        new DateTimeZone($timezone);

        $participantSecret = $this->generator->generate();
        $administratorSecret = $this->generator->generate();
        $recoverySecret = $this->generator->generate();
        $this->store->createRound(
            bin2hex(random_bytes(16)),
            $publicSlug,
            $name,
            $timezone,
            $this->digester->digest($participantSecret),
            $this->digester->digest($administratorSecret),
            $this->digester->digest($recoverySecret),
            $now,
        );

        return new InitialCredentialSet($participantSecret, $administratorSecret, $recoverySecret);
    }
}
