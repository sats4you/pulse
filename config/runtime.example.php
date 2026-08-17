<?php

declare(strict_types=1);

// Copy this file to runtime.php on the server and replace every placeholder.
// runtime.php is ignored by Git and must remain outside the public web root.
return [
    'APP_ENV' => 'production',
    'APP_BASE_URL' => 'https://sats4you.ch',
    'APP_HMAC_KEY' => 'replace-with-at-least-32-random-characters',
    'DB_DSN' => 'mysql:host=mysql.lima-city.de;dbname=replace-me;charset=utf8mb4',
    'DB_USER' => 'replace-me',
    'DB_PASSWORD' => 'replace-me',
    'NOTIFICATION_RECIPIENT' => 'admin@example.org',
    'NOTIFICATION_FROM' => 'pulse@example.org',
    'NOTIFICATION_LOCALE' => 'de',
];
