<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final class InvitationAccessTest extends TestCase
{
    public function testInvitationAndManagementSecretsCannotBeInterchanged(): void
    {
        $store = new InMemoryInvitationStore();
        $digester = new SecretDigester(str_repeat('d', 32));
        $created = (new InvitationCreator($store, new SecretGenerator(), $digester))->create(
            new InvitationInput('de', 'Andi', 'Nico', null, 5_000, true, true, 'v1'),
            $now = new DateTimeImmutable('2026-08-23T12:00:00+00:00'),
        );
        $access = new InvitationAccess($store, $digester, new AccessSessionCodec(str_repeat('s', 32)));

        self::assertNull($access->exchange($created->invitation->publicId, $created->invitationSecret, AccessRole::FirstSatsManagement, $now));
        self::assertNull($access->exchange($created->invitation->publicId, $created->managementSecret, AccessRole::FirstSatsInvitation, $now));
        $cookie = $access->exchange($created->invitation->publicId, $created->invitationSecret, AccessRole::FirstSatsInvitation, $now);
        self::assertNotNull($cookie);
        self::assertNotNull($access->authorise($created->invitation->publicId, $cookie, AccessRole::FirstSatsInvitation, $now));
        self::assertNull($access->authorise($created->invitation->publicId, $cookie, AccessRole::FirstSatsManagement, $now));
    }
}
