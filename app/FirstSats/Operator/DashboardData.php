<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Operator;

final readonly class DashboardData
{
    /** @param array<string,int> $funnel @param array<string,int> $wallets @param array<string,int> $survey */
    public function __construct(public array $funnel,public array $wallets,public array $survey,public string $surveyVersion){}
}
