<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Pulse\Retention\PdoRetentionStore;
use Sats4you\Pulse\Pulse\Retention\RetentionService;
use Sats4you\Pulse\FirstSats\Retention\PdoRetentionStore as FirstSatsRetentionStore;
use Sats4you\Pulse\FirstSats\Retention\RetentionService as FirstSatsRetentionService;
use Sats4you\Pulse\Platform\Operations\PdoOperationalHeartbeatStore;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
    $connection = ConnectionFactory::create(
        $configuration->required('DB_DSN'),
        $configuration->required('DB_USER'),
        $configuration->required('DB_PASSWORD'),
    );
    $now = new DateTimeImmutable();
    $report = (new RetentionService(new PdoRetentionStore($connection)))->run($now);
    $firstSats = (new FirstSatsRetentionService(new FirstSatsRetentionStore($connection)))->run($now);
    (new PdoOperationalHeartbeatStore($connection))->recordSuccess('retention', $now);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'attendance_deleted' => $report->attendanceDeleted,
        'events_deleted' => $report->eventsDeleted,
        'first_sats_handovers_closed' => $firstSats->handoversClosed,
        'first_sats_payment_requests_deleted' => $firstSats->paymentRequestsDeleted,
        'first_sats_invitations_scheduled' => $firstSats->invitationsScheduled,
        'first_sats_invitations_deleted' => $firstSats->invitationsDeleted,
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    error_log('[pulse-retention] failed: ' . $error::class);
    fwrite(STDERR, "{\"ok\":false,\"error\":\"retention_failed\"}\n");
    exit(1);
}
