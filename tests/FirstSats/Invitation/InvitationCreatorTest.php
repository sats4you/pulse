<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final class InvitationCreatorTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function testCreatesOneInvitationWithSeparateHashedSecrets(int $amount): void
    {
        $store = new InMemoryInvitationStore();
        $digester = new SecretDigester(str_repeat('k', 32));
        $creator = new InvitationCreator($store, new SecretGenerator(), $digester);
        $now = new DateTimeImmutable('2026-08-23T10:00:00+00:00');

        $result = $creator->create(new InvitationInput(
            'de', 'Götti Andi', 'Nico', 'Hallo Nico', $amount, true, true, 'inviter-2026-08-22', true,
        ), $now);

        self::assertCount(1, $store->invitations);
        self::assertSame($amount, $result->invitation->satsAmount);
        self::assertEquals($now->modify('+30 days'), $result->invitation->acceptUntil);
        self::assertNotSame($result->invitationSecret, $result->managementSecret);
        self::assertNotSame($result->managementSecret, $result->recoveryCode);
        self::assertTrue($digester->matches($result->invitationSecret, $result->invitation->invitationAccessDigest));
        self::assertTrue($digester->matches($result->managementSecret, $result->invitation->managementAccessDigest));
        self::assertTrue($digester->matches($result->recoveryCode, $result->invitation->recoveryDigest));
        self::assertStringNotContainsString($result->invitationSecret, serialize($result->invitation));
    }

    /** @return array<string, array{int}> */
    public static function validAmounts(): array
    {
        return ['zero' => [0], 'one' => [1], 'maximum' => [100_000]];
    }

    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(InvitationInput $input, string $reason): void
    {
        $creator = new InvitationCreator(
            new InMemoryInvitationStore(),
            new SecretGenerator(),
            new SecretDigester(str_repeat('k', 32)),
        );
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($reason);
        $creator->create($input, new DateTimeImmutable());
    }

    /** @return array<string, array{InvitationInput, string}> */
    public static function invalidInput(): array
    {
        $valid = fn (
            string $locale = 'de', string $inviter = 'Andi', string $invitee = 'Nico',
            int $sats = 5_000, bool $adult = true, bool $terms = true,
            bool $highAmount = true,
        ): InvitationInput => new InvitationInput($locale, $inviter, $invitee, null, $sats, $adult, $terms, 'v1', $highAmount);

        return [
            'negative sats' => [$valid(sats: -1), 'invalid_sats'],
            'above maximum' => [$valid(sats: 100_001), 'invalid_sats'],
            'high amount not acknowledged' => [$valid(sats: 50_000, highAmount: false), 'high_amount_acknowledgement_required'],
            'missing inviter label' => [$valid(inviter: ' '), 'invalid_label'],
            'label with link' => [$valid(invitee: 'https://example.org'), 'invalid_label'],
            'unsupported locale' => [$valid(locale: 'en'), 'invalid_locale'],
            'adult not attested' => [$valid(adult: false), 'adult_attestation_required'],
            'terms not accepted' => [$valid(terms: false), 'terms_required'],
        ];
    }
}
