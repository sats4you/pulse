<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\FirstSats\Invitation\InvitationLifecycle;
use Sats4you\Pulse\FirstSats\Invitation\ParticipantJourney;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final class InvitationLifecycleTest extends TestCase
{
    public function testExtendsAndRevokesOnlyOpenInvitation(): void
    {
        [$store,$created,$lifecycle,$access,$now]=$this->fixture();
        self::assertNull($access->exchange($created->invitation->publicId,$created->invitationSecret,AccessRole::FirstSatsInvitation,$now->modify('+1 second')));
        $extended=$lifecycle->extend($created->invitation,$now);
        self::assertSame('2026-10-22',$extended?->acceptUntil->format('Y-m-d'));
        $revoked=$lifecycle->revoke($extended,$now);
        self::assertSame('revoked',$revoked?->status);
        self::assertNull($access->exchange($created->invitation->publicId,$created->invitationSecret,AccessRole::FirstSatsInvitation,$now));
        self::assertNull($lifecycle->extend($revoked,$now));
    }

    public function testResetClearsProgressAndInvalidatesEveryOldParticipantAccess(): void
    {
        [$store,$created,$lifecycle,$access,$now,$secrets,$digester]=$this->fixture();
        $initial=$access->exchange($created->invitation->publicId,$created->invitationSecret,AccessRole::FirstSatsInvitation,$now);
        self::assertNotNull($initial);
        $accepted=(new ParticipantJourney($store,$secrets,$digester))->accept($created->invitation,true,true,'v1',$now);
        self::assertNotNull($accepted);
        $continuation=(new ParticipantJourney($store,$secrets,$digester))->createContinuation($accepted,$now);
        self::assertNotNull($continuation);
        $continuationSession=$access->session($continuation->invitation,AccessRole::FirstSatsInvitation,$now);

        $reset=$lifecycle->reset($continuation->invitation,$now);
        self::assertNotNull($reset);
        self::assertSame('open',$reset->invitation->status);
        self::assertNull($reset->invitation->acceptedAt);
        self::assertNull($reset->invitation->stageOneCompletedAt);
        self::assertNull($access->authorise($created->invitation->publicId,$continuationSession,AccessRole::FirstSatsInvitation,$now));
        self::assertNull($access->exchange($created->invitation->publicId,$continuation->secret,AccessRole::FirstSatsInvitation,$now));
        self::assertNotNull($access->exchange($created->invitation->publicId,$reset->invitationSecret,AccessRole::FirstSatsInvitation,$now));
    }

    public function testParticipantCanEndOnlyBeforeCompletionAndEveryParticipantAccessIsRevoked():void
    {
        [$store,$created,,$access,$now,$secrets,$digester]=$this->fixture();
        $journey=new ParticipantJourney($store,$secrets,$digester);
        $accepted=$journey->accept($created->invitation,true,true,'v1',$now);
        self::assertNotNull($accepted);
        $session=$access->session($accepted,AccessRole::FirstSatsInvitation,$now);
        $continuation=$journey->createContinuation($accepted,$now);
        self::assertNotNull($continuation);
        $ended=$journey->end($continuation->invitation,$now->modify('+1 minute'));
        self::assertSame('participant_ended',$ended?->status);
        self::assertNull($ended?->acceptedAt);
        self::assertNull($ended?->stageOneCompletedAt);
        self::assertNull($access->authorise($ended->publicId,$session,AccessRole::FirstSatsInvitation,$now->modify('+2 minutes')));
        self::assertNull($access->exchange($ended->publicId,$continuation->secret,AccessRole::FirstSatsInvitation,$now->modify('+2 minutes')));

        $acceptedAgain=$created->invitation->accepted('digest',2,'v1',$now,$now->modify('+30 days'))
            ->withStageOneCompleted($now)->withFractionSurveyState('skipped')->withStageTwoCompleted($now)
            ->withStageThreeCompleted($now)->withStageFourCompleted($now);
        self::assertNull($journey->end($acceptedAgain,$now));
    }

    /** @return array{InMemoryInvitationStore,mixed,InvitationLifecycle,InvitationAccess,DateTimeImmutable,SecretGenerator,SecretDigester} */
    private function fixture():array
    {
        $store=new InMemoryInvitationStore();$secrets=new SecretGenerator();$digester=new SecretDigester(str_repeat('l',32));$now=new DateTimeImmutable('2026-09-22T12:00:00+00:00');
        $created=(new InvitationCreator($store,$secrets,$digester))->create(new InvitationInput('de','Andi','Nico',null,5000,true,true,'v1'),new DateTimeImmutable('2026-08-23T12:00:00+00:00'));
        return [$store,$created,new InvitationLifecycle($store,$secrets,$digester),new InvitationAccess($store,$digester,new AccessSessionCodec(str_repeat('s',32))),$now,$secrets,$digester];
    }
}
