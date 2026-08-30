<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\FirstSats\Invitation\InvitationRecovery;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final class InvitationRecoveryTest extends TestCase
{
    public function testAtomicallyRotatesManagementAndRecoveryCredentials(): void
    {
        $store = new InMemoryInvitationStore();
        $digester = new SecretDigester(str_repeat('r', 32));
        $secrets = new SecretGenerator();
        $now = new DateTimeImmutable('2026-08-23T12:00:00+00:00');
        $created = (new InvitationCreator($store, $secrets, $digester))->create(
            new InvitationInput('de', 'Andi', 'Nico', null, 5_000, true, true, 'v1'), $now,
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}\.[A-Za-z0-9_-]{43}$/', $created->recoveryCode);

        $access = new InvitationAccess($store, $digester, new AccessSessionCodec(str_repeat('s', 32)));
        $oldSession = $access->exchange($created->invitation->publicId, $created->managementSecret, AccessRole::FirstSatsManagement, $now);
        self::assertNotNull($oldSession);

        $recovery = new InvitationRecovery($store, $secrets, $digester);
        $result = $recovery->recover($created->recoveryCode, $now->modify('+1 minute'));
        self::assertNotNull($result);
        self::assertSame(2, $result->invitation->managementAccessVersion);
        self::assertSame(2, $result->invitation->recoveryVersion);
        self::assertNull($access->authorise($created->invitation->publicId, $oldSession, AccessRole::FirstSatsManagement, $now->modify('+2 minutes')));
        self::assertNull($access->exchange($created->invitation->publicId, $created->managementSecret, AccessRole::FirstSatsManagement, $now));
        self::assertNull($recovery->recover($created->recoveryCode, $now->modify('+2 minutes')));
        self::assertNotNull($access->exchange($created->invitation->publicId, $result->managementSecret, AccessRole::FirstSatsManagement, $now));
        self::assertNotNull($recovery->recover($result->recoveryCode, $now->modify('+3 minutes')));
    }

    public function testRejectsMalformedCodeWithoutLookingUpAnInvitation(): void
    {
        $store = new InMemoryInvitationStore();
        $recovery = new InvitationRecovery($store, new SecretGenerator(), new SecretDigester(str_repeat('r', 32)));
        self::assertNull($recovery->recover('not-a-recovery-code', new DateTimeImmutable()));
        self::assertSame([], $store->invitations);
    }
}
