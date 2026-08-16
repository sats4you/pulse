<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

final readonly class ParticipantRotationResult
{
    public function __construct(public string $participantSecret)
    {
    }
}
