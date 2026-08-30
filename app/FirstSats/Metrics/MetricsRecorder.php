<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Metrics;

use DateTimeImmutable;

interface MetricsRecorder
{
    public function record(string $publicId,string $metricKey,DateTimeImmutable $at,string $answerKey=''):bool;
}
