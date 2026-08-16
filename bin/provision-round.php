<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Credentials\PdoRoundProvisioningStore;
use Sats4you\Pulse\Pulse\Credentials\RoundProvisioningService;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
if ($argc !== 3) {
    fwrite(STDERR, "Verwendung: php bin/provision-round.php <slug> <gruppenname>\n");
    exit(2);
}

$configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
$baseUrl = rtrim($configuration->required('APP_BASE_URL'), '/');
$hmacKey = $configuration->required('APP_HMAC_KEY');
$connection = ConnectionFactory::create(
    $configuration->required('DB_DSN'),
    $configuration->required('DB_USER'),
    $configuration->required('DB_PASSWORD'),
);
$result = (new RoundProvisioningService(
    new PdoRoundProvisioningStore($connection),
    new SecretGenerator(),
    new SecretDigester($hmacKey),
))->provision($argv[1], $argv[2], 'Europe/Zurich', new DateTimeImmutable());

$slug = rawurlencode($argv[1]);
fwrite(STDOUT, "Einmalige Zugangsausgabe – jetzt sicher und getrennt speichern.\n\n");
fwrite(STDOUT, "Teilnehmerlink:\n{$baseUrl}/pulse/r/{$slug}#{$result->participantSecret}\n\n");
fwrite(STDOUT, "Verwaltungslink:\n{$baseUrl}/pulse/manage/r/{$slug}#{$result->administratorSecret}\n\n");
fwrite(STDOUT, "Wiederherstellungscode:\n{$result->recoverySecret}\n\n");
fwrite(STDOUT, "Wiederherstellungslink:\n{$baseUrl}/pulse/recover/r/{$slug}#{$result->recoverySecret}\n\n");
fwrite(STDOUT, "Die lesbaren Geheimnisse sind nicht in der Datenbank gespeichert und können nicht erneut angezeigt werden.\n");
