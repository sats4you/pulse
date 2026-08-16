<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Pulse\Retention\PdoRetentionStore;
use Sats4you\Pulse\Pulse\Retention\RetentionService;

require dirname(__DIR__) . '/vendor/autoload.php';

$configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
$connection = ConnectionFactory::create(
    $configuration->required('DB_DSN'),
    $configuration->required('DB_USER'),
    $configuration->required('DB_PASSWORD'),
);
$report = (new RetentionService(new PdoRetentionStore($connection)))->run(new DateTimeImmutable());

fwrite(STDOUT, json_encode([
    'ok' => true,
    'attendance_deleted' => $report->attendanceDeleted,
    'events_deleted' => $report->eventsDeleted,
], JSON_THROW_ON_ERROR) . PHP_EOL);
