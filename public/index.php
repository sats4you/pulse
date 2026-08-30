<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Database\ConnectionFactory;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;
use Sats4you\Pulse\Platform\Http\PulseApplicationFactory;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\FirstSats\Http\FirstSatsPage;
use Sats4you\Pulse\FirstSats\Http\FirstSatsRoutes;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationRecovery;
use Sats4you\Pulse\FirstSats\Invitation\InvitationLifecycle;
use Sats4you\Pulse\FirstSats\Invitation\PdoInvitationStore;
use Sats4you\Pulse\FirstSats\Invitation\ParticipantJourney;
use Sats4you\Pulse\FirstSats\Learning\LearningJourney;
use Sats4you\Pulse\FirstSats\Wallet\WalletJourney;
use Sats4you\Pulse\FirstSats\Payment\Bolt11Inspector;
use Sats4you\Pulse\FirstSats\Payment\PdoPaymentRequestStore;
use Sats4you\Pulse\FirstSats\Payment\PdoHandoverStore;
use Sats4you\Pulse\FirstSats\Metrics\PdoMetricsRecorder;
use Sats4you\Pulse\FirstSats\Pricing\KrakenTickerParser;
use Sats4you\Pulse\FirstSats\Pricing\PdoCachedChfQuoteProvider;
use Sats4you\Pulse\FirstSats\Pricing\StreamKrakenTickerClient;
use Sats4you\Pulse\FirstSats\Operator\OperatorAccess;
use Sats4you\Pulse\FirstSats\Operator\OperatorPage;
use Sats4you\Pulse\FirstSats\Operator\OperatorRoutes;
use Sats4you\Pulse\FirstSats\Operator\PdoDashboardStore;
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
if ($configuration->optional('APP_MAINTENANCE') === '1') {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Wartungsarbeiten · sats4you.ch</title><body style="font-family:system-ui,sans-serif;max-width:38rem;margin:4rem auto;padding:1.5rem;line-height:1.5">'
        . '<h1>Kurze Wartungsarbeiten</h1><p>sats4you.ch ist vorübergehend nicht erreichbar. Bitte versuche es später nochmals.</p></body></html>';
    exit;
}
$baseUrl = rtrim($configuration->required('APP_BASE_URL'), '/');
$hmacKey = $configuration->required('APP_HMAC_KEY');
$firstSatsPublicLocales = array_values(array_filter(
    array_map('trim', explode(',', $configuration->required('FIRST_SATS_PUBLIC_LOCALES'))),
    static fn (string $locale): bool => $locale !== '',
));
$connection = ConnectionFactory::create(
    $configuration->required('DB_DSN'),
    $configuration->required('DB_USER'),
    $configuration->required('DB_PASSWORD'),
);
$digester = new SecretDigester($hmacKey);
$secretGenerator = new SecretGenerator();
$firstSatsMetrics = new PdoMetricsRecorder($connection);
$firstSatsChfQuotes = new PdoCachedChfQuoteProvider($connection, new StreamKrakenTickerClient(new KrakenTickerParser()));
$attendanceStore = new PdoAttendanceStore($connection);
$policy = new EventAccessPolicy();
$attendanceService = new AttendanceService(
    $attendanceStore,
    $policy,
    new RetentionSchedule(),
    $secretGenerator,
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
    new FirstSatsRoutes(
        new InvitationCreator(
            $firstSatsStore = new PdoInvitationStore($connection),
            $secretGenerator,
            $digester,
        ),
        new InvitationRecovery($firstSatsStore, $secretGenerator, $digester),
        new InvitationLifecycle($firstSatsStore, $secretGenerator, $digester),
        new InvitationAccess(
            $firstSatsStore,
            $digester,
            new AccessSessionCodec(hash_hmac('sha256', 'first-sats-access-session', $hmacKey, true)),
        ),
        new ParticipantJourney($firstSatsStore, $secretGenerator, $digester),
        new LearningJourney($firstSatsStore),
        new WalletJourney($firstSatsStore),
        new Bolt11Inspector(),
        new PdoPaymentRequestStore($connection),
        new PdoHandoverStore($connection),
        new FirstSatsPage($firstSatsChfQuotes),
        new SameOriginGuard($baseUrl),
        new CsrfToken(hash_hmac('sha256', 'first-sats-csrf', $hmacKey, true)),
        $secretGenerator,
        dirname(__DIR__) . '/resources/first-sats',
        $baseUrl,
        str_starts_with($baseUrl, 'https://'),
        $firstSatsMetrics,
        $firstSatsPublicLocales,
    ),
))->create();

$operatorDigestHex = $configuration->optional('FIRST_SATS_OPERATOR_SECRET_HASH');
if ($operatorDigestHex !== null) {
    if (preg_match('/^[a-f0-9]{64}$/', $operatorDigestHex) !== 1) {
        throw new RuntimeException('FIRST_SATS_OPERATOR_SECRET_HASH must contain 64 lowercase hexadecimal characters.');
    }

    (new OperatorRoutes(
        new OperatorAccess(
            hex2bin($operatorDigestHex),
            $digester,
            new AccessSessionCodec(hash_hmac('sha256', 'first-sats-operator-session', $hmacKey, true)),
        ),
        new PdoDashboardStore($connection),
        new OperatorPage(),
        new SameOriginGuard($baseUrl),
        dirname(__DIR__) . '/resources/first-sats',
        $baseUrl,
        str_starts_with($baseUrl, 'https://'),
    ))->register($app);
}
$app->run();
