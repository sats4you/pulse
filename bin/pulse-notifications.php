<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\Notifications\NotificationDispatcher;
use Sats4you\Pulse\Pulse\Notifications\NotificationMessageFactory;
use Sats4you\Pulse\Pulse\Notifications\PdoNotificationOutboxStore;
use Sats4you\Pulse\Pulse\Notifications\PhpMailNotificationMailer;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__);
$configuration = RuntimeConfiguration::fromProjectRoot($projectRoot);
$locale = $configuration->required('NOTIFICATION_LOCALE');
if (!in_array($locale, ['de', 'fr', 'it', 'rm'], true)) {
    fwrite(STDERR, "FEHLER: Ungültige Benachrichtigungssprache.\n");
    exit(1);
}

$connection = ConnectionFactory::create(
    $configuration->required('DB_DSN'),
    $configuration->required('DB_USER'),
    $configuration->required('DB_PASSWORD'),
);
$dispatcher = new NotificationDispatcher(
    new PdoNotificationOutboxStore($connection),
    new PhpMailNotificationMailer(
        $configuration->required('NOTIFICATION_RECIPIENT'),
        $configuration->required('NOTIFICATION_FROM'),
    ),
    new NotificationMessageFactory(
        TranslatorFactory::create($locale, $projectRoot . '/resources/translations'),
        $locale,
        new DateTimeZone('Europe/Zurich'),
    ),
    new DateInterval('PT5M'),
);
$report = $dispatcher->dispatch(new DateTimeImmutable());

fwrite(STDOUT, json_encode([
    'ok' => $report->failed === 0,
    'sent' => $report->sent,
    'failed' => $report->failed,
    'expired' => $report->expired,
    'lock_acquired' => $report->lockAcquired,
], JSON_THROW_ON_ERROR) . PHP_EOL);

exit($report->failed === 0 ? 0 : 1);
