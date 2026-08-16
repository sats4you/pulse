<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Http\PulseApplicationFactory;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Attendance\PdoAttendanceStore;
use Sats4you\Pulse\Pulse\Administration\AdminBootstrapPage;
use Sats4you\Pulse\Pulse\Administration\AdminEventService;
use Sats4you\Pulse\Pulse\Administration\AdminPage;
use Sats4you\Pulse\Pulse\Administration\PdoAdminEventStore;
use Sats4you\Pulse\Pulse\Credentials\CredentialPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialRotationService;
use Sats4you\Pulse\Pulse\Credentials\PdoCredentialStore;
use Sats4you\Pulse\Pulse\Credentials\RecoveryBootstrapPage;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantFlow;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantPage;
use Sats4you\Pulse\Pulse\PublicAccess\PdoPublishedEventStore;
use Sats4you\Pulse\Pulse\PublicAccess\PdoRoundAccessStore;
use Sats4you\Pulse\Pulse\PublicAccess\PulseLandingPage;
use Sats4you\Pulse\Pulse\Privacy\PrivacyPage;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;

require dirname(__DIR__) . '/vendor/autoload.php';

$configuration = RuntimeConfiguration::fromProjectRoot(dirname(__DIR__));
$baseUrl = rtrim($configuration->required('APP_BASE_URL'), '/');
$hmacKey = $configuration->required('APP_HMAC_KEY');
$connection = ConnectionFactory::create(
    $configuration->required('DB_DSN'),
    $configuration->required('DB_USER'),
    $configuration->required('DB_PASSWORD'),
);
$digester = new SecretDigester($hmacKey);
$attendanceStore = new PdoAttendanceStore($connection);
$policy = new EventAccessPolicy();
$attendanceService = new AttendanceService(
    $attendanceStore,
    $policy,
    new RetentionSchedule(),
    new SecretGenerator(),
    $digester,
);
$accessExchange = new AccessExchange(
    new PdoRoundAccessStore($connection),
    $digester,
    new AccessSessionCodec(hash_hmac('sha256', 'pulse-access-session', $hmacKey, true)),
);
$participantFlow = new ParticipantFlow(
    new PdoPublishedEventStore($connection),
    $attendanceStore,
    $attendanceService,
    $digester,
);

$app = (new PulseApplicationFactory(
    $accessExchange,
    $participantFlow,
    new AdminEventService(new PdoAdminEventStore($connection), new RetentionSchedule()),
    new CredentialRotationService(new PdoCredentialStore($connection), new SecretGenerator(), $digester),
    new BootstrapPage(),
    new ParticipantPage($policy),
    new PulseLandingPage(),
    new AdminBootstrapPage(),
    new AdminPage(),
    new RecoveryBootstrapPage(),
    new CredentialPage(),
    new PrivacyPage(),
    new SameOriginGuard($baseUrl),
    new CsrfToken(hash_hmac('sha256', 'pulse-csrf', $hmacKey, true)),
    dirname(__DIR__) . '/resources/translations',
    'Bern Monthly Bitcoin Meetup',
    $baseUrl,
    str_starts_with($baseUrl, 'https://'),
))->create();
$app->run();
