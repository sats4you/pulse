<?php

declare(strict_types=1);

use Sats4you\Pulse\FirstSats\Retention\PdoPostRestorePurger;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$confirmation = '--confirm-delete-restored-first-sats-invitations';
if (!in_array($confirmation, $_SERVER['argv'] ?? [], true)) {
    fwrite(STDERR, "Abbruch: ausdrückliche Bestätigung fehlt. Dieses Kommando ist nur nach einem Datenbank-Restore im Wartungsmodus zulässig.\n");
    exit(2);
}

try {
    $configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
    if ($configuration->required('APP_MAINTENANCE') !== '1') {
        fwrite(STDERR, "Abbruch: APP_MAINTENANCE muss 1 sein.\n");
        exit(2);
    }

    $connection = ConnectionFactory::create(
        $configuration->required('DB_DSN'),
        $configuration->required('DB_USER'),
        $configuration->required('DB_PASSWORD'),
    );
    $deleted = (new PdoPostRestorePurger($connection))->purgeInvitationLinkedData();
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'first_sats_invitations_deleted' => $deleted,
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    error_log('[first-sats-post-restore-purge] failed: ' . $error::class);
    fwrite(STDERR, "{\"ok\":false,\"error\":\"post_restore_purge_failed\"}\n");
    exit(1);
}
