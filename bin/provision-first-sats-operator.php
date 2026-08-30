<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

$configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
$secret = (new SecretGenerator())->generate();
$digest = (new SecretDigester($configuration->required('APP_HMAC_KEY')))->digest($secret);

fwrite(STDOUT, "Operator secret (store offline; shown once):\n" . $secret . "\n\n");
fwrite(STDOUT, "FIRST_SATS_OPERATOR_SECRET_HASH:\n" . bin2hex($digest) . "\n");
