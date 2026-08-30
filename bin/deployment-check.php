<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
$errors = [];

if (PHP_VERSION_ID < 80200) {
    $errors[] = 'PHP 8.2 oder neuer ist erforderlich.';
}

$enabled = static fn (string $value): bool => in_array(strtolower(trim($value)), ['1', 'on', 'true', 'yes'], true);
if ($enabled((string) ini_get('display_errors'))) {
    $errors[] = 'display_errors muss in Produktion deaktiviert sein.';
}
if (!$enabled((string) ini_get('log_errors'))) {
    $errors[] = 'log_errors muss in Produktion aktiviert sein.';
}
if ($enabled((string) ini_get('expose_php'))) {
    $errors[] = 'expose_php muss in Produktion deaktiviert sein.';
}

foreach (['json', 'intl', 'mbstring', 'pdo', 'pdo_mysql'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = "PHP-Erweiterung fehlt: {$extension}.";
    }
}

try {
    if ($configuration->required('APP_ENV') !== 'production') {
        $errors[] = 'APP_ENV muss für die Veröffentlichung production sein.';
    }
    $maintenance = $configuration->required('APP_MAINTENANCE');
    if (!in_array($maintenance, ['0', '1'], true)) {
        $errors[] = 'APP_MAINTENANCE muss ausdrücklich 0 oder 1 sein.';
    } elseif ($maintenance !== '0') {
        $errors[] = 'APP_MAINTENANCE ist noch aktiv.';
    }
    $baseUrl = $configuration->required('APP_BASE_URL');
    if (!str_starts_with($baseUrl, 'https://')) {
        $errors[] = 'APP_BASE_URL muss im Pilot mit https:// beginnen.';
    }

    if (strlen($configuration->required('APP_HMAC_KEY')) < 32) {
        $errors[] = 'APP_HMAC_KEY muss mindestens 32 Zeichen lang sein.';
    }

    $publicLocales = array_values(array_filter(
        array_map('trim', explode(',', $configuration->required('FIRST_SATS_PUBLIC_LOCALES'))),
        static fn (string $locale): bool => $locale !== '',
    ));
    if ($publicLocales !== ['de', 'fr', 'it', 'rm']) {
        $errors[] = 'FIRST_SATS_PUBLIC_LOCALES muss für die öffentliche Beta exakt de,fr,it,rm sein.';
    }

    $connection = ConnectionFactory::create(
        $configuration->required('DB_DSN'),
        $configuration->required('DB_USER'),
        $configuration->required('DB_PASSWORD'),
    );
    $connection->query('SELECT 1');

    $requiredTables = [
        'coordination_rounds',
        'coordination_events',
        'attendance_commitments',
        'attendance_notification_changes',
        'first_sats_invitations',
        'first_sats_anonymous_counters',
        'first_sats_payment_requests',
        'first_sats_handover_states',
        'first_sats_metric_contributions',
        'first_sats_chf_quote_cache',
        'operational_heartbeats',
    ];
    $availableTables = [];
    foreach ($requiredTables as $table) {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);
        if ((int) $statement->fetchColumn() !== 1) {
            $errors[] = "Datenbanktabelle fehlt: {$table}.";
        } else {
            $availableTables[$table] = true;
        }
    }

    if (isset($availableTables['operational_heartbeats'])) {
        $statement = $connection->prepare(
            'SELECT last_succeeded_at FROM operational_heartbeats WHERE operation_key = :operation'
        );
        $statement->execute(['operation' => 'retention']);
        $lastRetention = $statement->fetchColumn();
        if (!is_string($lastRetention)) {
            $errors[] = 'Der Löschlauf hat noch keinen erfolgreichen Kontrollzeitpunkt geschrieben.';
        } elseif (new DateTimeImmutable($lastRetention) < (new DateTimeImmutable())->sub(new DateInterval('PT26H'))) {
            $errors[] = 'Der letzte erfolgreiche Löschlauf liegt mehr als 26 Stunden zurück.';
        }
    }

    foreach (['NOTIFICATION_RECIPIENT', 'NOTIFICATION_FROM'] as $mailSetting) {
        if (filter_var($configuration->required($mailSetting), FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Ungültige E-Mail-Konfiguration: {$mailSetting}.";
        }
    }
    if (!in_array($configuration->required('NOTIFICATION_LOCALE'), ['de', 'fr', 'it', 'rm'], true)) {
        $errors[] = 'NOTIFICATION_LOCALE muss de, fr, it oder rm sein.';
    }
    if (preg_match('/^[a-f0-9]{64}$/', $configuration->required('FIRST_SATS_OPERATOR_SECRET_HASH')) !== 1) {
        $errors[] = 'FIRST_SATS_OPERATOR_SECRET_HASH muss ein HMAC-Prüfwert mit 64 kleinen Hex-Zeichen sein.';
    }
} catch (Throwable) {
    $errors[] = 'Konfiguration oder Datenbankverbindung ist nicht bereit. Details stehen nur in der lokalen Serverausgabe.';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FEHLER: {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: PHP, Erweiterungen, HTTPS-Konfiguration und Datenbankschema sind bereit.\n");
