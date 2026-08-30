<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateInterval;
use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final readonly class InvitationLifecycle
{
    public function __construct(private InvitationStore $store,private SecretGenerator $secrets,private SecretDigester $digester) {}

    public function extend(Invitation $i,DateTimeImmutable $now):?Invitation
    {
        $oldest=$now->sub(new DateInterval('P30D'));
        if($i->status==='open'&&$i->acceptUntil>=$oldest){
            $base=$i->acceptUntil>$now?$i->acceptUntil:$now;
            return $this->store->extendOpen($i->publicId,$i->managementAccessVersion,$base->add(new DateInterval('P30D')),$now);
        }
        if($i->status==='accepted'&&$i->stageFourCompletedAt===null&&$i->completeUntil!==null&&$i->completeUntil>=$oldest){
            $base=$i->completeUntil>$now?$i->completeUntil:$now;
            return $this->store->extendAccepted($i->publicId,$i->managementAccessVersion,$base->add(new DateInterval('P30D')),$now);
        }
        return null;
    }

    public function revoke(Invitation $i,DateTimeImmutable $now):?Invitation
    {
        if($i->status!=='open')return null;
        return $this->store->revokeOpen($i->publicId,$i->managementAccessVersion,$this->digester->digest($this->secrets->generate()),$now,$now->add(new DateInterval('P30D')));
    }

    public function reset(Invitation $i,DateTimeImmutable $now):?ResetCredentials
    {
        if($i->status!=='accepted')return null;
        $secret=$this->secrets->generate();
        $reset=$this->store->resetParticipation($i->publicId,$i->managementAccessVersion,$this->digester->digest($secret),$now->add(new DateInterval('P30D')),$now);
        return $reset===null?null:new ResetCredentials($reset,$secret);
    }
}
