<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Operator;

use DateInterval;
use DateTimeImmutable;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSession;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;

final readonly class OperatorAccess
{
    private const SUBJECT='first-sats-operator';
    public function __construct(private string $digest,private SecretDigester $digester,private AccessSessionCodec $sessions){}
    public function exchange(string $secret,DateTimeImmutable $now):?string
    {
        if($secret===''||!$this->digester->matches($secret,$this->digest))return null;
        return $this->sessions->encode(new AccessSession(self::SUBJECT,AccessRole::FirstSatsOperator,1,$now->add(new DateInterval('PT2H'))));
    }
    public function authorise(string $cookie,DateTimeImmutable $now):bool
    {
        $session=$this->sessions->decode($cookie,$now);
        return $session!==null&&$session->roundId===self::SUBJECT&&$session->role===AccessRole::FirstSatsOperator&&$session->accessVersion===1;
    }
}
