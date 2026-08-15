<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

enum AccessRole: string
{
    case Participant = 'participant';
    case Administrator = 'administrator';
    case Recovery = 'recovery';
}
