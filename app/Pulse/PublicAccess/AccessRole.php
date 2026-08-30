<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

enum AccessRole: string
{
    case Participant = 'participant';
    case Administrator = 'administrator';
    case Recovery = 'recovery';
    case FirstSatsInvitation = 'first_sats_invitation';
    case FirstSatsManagement = 'first_sats_management';
    case FirstSatsOperator = 'first_sats_operator';
}
