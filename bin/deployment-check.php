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

foreach (['json', 'intl', 'mbstring', 'pdo', 'pdo_mysql'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = "PHP-Erweiterung fehlt: {$extension}.";
    }
}

try {
    $baseUrl = $configuration->required('APP_BASE_URL');
    if (!str_starts_with($baseUrl, 'https://')) {
        $errors[] = 'APP_BASE_URL muss im Pilot mit https:// beginnen.';
    }

    if (strlen($configuration->required('APP_HMAC_KEY')) < 32) {
        $errors[] = 'APP_HMAC_KEY muss mindestens 32 Zeichen lang sein.';
    }

    $connection = ConnectionFactory::create(
        $configuration->required('DB_DSN'),
        $configuration->required('DB_USER'),
        $configuration->required('DB_PASSWORD'),
    );
    $connection->query('SELECT 1');

    foreach (['coordination_rounds', 'coordination_events', 'attendance_commitments'] as $table) {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);
        if ((int) $statement->fetchColumn() !== 1) {
            $errors[] = "Datenbanktabelle fehlt: {$table}.";
        }
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
