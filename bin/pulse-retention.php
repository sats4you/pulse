<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Pulse\Retention\PdoRetentionStore;
use Sats4you\Pulse\Pulse\Retention\RetentionService;

require dirname(__DIR__) . '/vendor/autoload.php';

$required = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing required environment configuration: ' . $name);
    }

    return $value;
};

$connection = ConnectionFactory::create($required('DB_DSN'), $required('DB_USER'), $required('DB_PASSWORD'));
$report = (new RetentionService(new PdoRetentionStore($connection)))->run(new DateTimeImmutable());

fwrite(STDOUT, json_encode([
    'ok' => true,
    'attendance_deleted' => $report->attendanceDeleted,
    'events_deleted' => $report->eventsDeleted,
], JSON_THROW_ON_ERROR) . PHP_EOL);
