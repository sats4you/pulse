<?php

declare(strict_types=1);

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
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\SecurityHeadersMiddleware;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Slim\Factory\AppFactory;

$public = dirname(__DIR__, 2) . '/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && str_starts_with($path, '/assets/') && is_file($public . $path)) {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'woff2' => 'font/woff2',
    ];
    header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    readfile($public . $path);
    return true;
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$connection = new \PDO('sqlite:' . dirname(__DIR__, 2) . '/var/first-sats-preview.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_invitations (id TEXT PRIMARY KEY, public_id TEXT UNIQUE, locale TEXT, inviter_label TEXT, invitee_label TEXT, personal_message TEXT, sats_amount INTEGER, invitation_access_digest BLOB, invitation_access_version INTEGER, management_access_digest BLOB, management_access_version INTEGER, recovery_digest BLOB, recovery_version INTEGER, status TEXT, inviter_adult_attested INTEGER, inviter_terms_version TEXT, inviter_terms_accepted_at TEXT, participant_adult_attested INTEGER NULL, participant_terms_version TEXT NULL, participant_terms_accepted_at TEXT NULL, accepted_at TEXT NULL, complete_until TEXT NULL, learning_content_version TEXT NOT NULL DEFAULT \'2026-08-23-v1\', stage_one_completed_at TEXT NULL, fraction_survey_state TEXT NOT NULL DEFAULT \'pending\', stage_two_completed_at TEXT NULL, stage_three_completed_at TEXT NULL, stage_four_completed_at TEXT NULL, wallet_path TEXT NULL, created_at TEXT, accept_until TEXT, updated_at TEXT, delete_at TEXT NULL)');
$existingColumns = array_column($connection->query('PRAGMA table_info(first_sats_invitations)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach (['participant_adult_attested' => 'INTEGER NULL', 'participant_terms_version' => 'TEXT NULL', 'participant_terms_accepted_at' => 'TEXT NULL', 'accepted_at' => 'TEXT NULL', 'complete_until' => 'TEXT NULL', 'learning_content_version' => "TEXT NOT NULL DEFAULT '2026-08-23-v1'", 'stage_one_completed_at' => 'TEXT NULL', 'fraction_survey_state' => "TEXT NOT NULL DEFAULT 'pending'", 'stage_two_completed_at' => 'TEXT NULL', 'stage_three_completed_at' => 'TEXT NULL', 'stage_four_completed_at' => 'TEXT NULL', 'wallet_path' => 'TEXT NULL', 'delete_at' => 'TEXT NULL'] as $column => $definition) {
    if (!in_array($column, $existingColumns, true)) {
        $connection->exec('ALTER TABLE first_sats_invitations ADD COLUMN ' . $column . ' ' . $definition);
    }
}
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_anonymous_counters (metric_key TEXT NOT NULL, content_version TEXT NOT NULL, locale TEXT NOT NULL, answer_key TEXT NOT NULL, count_value INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL, PRIMARY KEY (metric_key, content_version, locale, answer_key))');
$connection->exec('PRAGMA foreign_keys = ON');
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_metric_contributions (invitation_id TEXT NOT NULL, metric_key TEXT NOT NULL, content_version TEXT NOT NULL, contributed_at TEXT NOT NULL, PRIMARY KEY (invitation_id, metric_key, content_version), FOREIGN KEY (invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE)');
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_chf_quote_cache (cache_key TEXT PRIMARY KEY, bid_units INTEGER NOT NULL, ask_units INTEGER NOT NULL, fetched_at TEXT NOT NULL)');
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_payment_requests (invitation_id TEXT PRIMARY KEY, bolt11_invoice TEXT NOT NULL, sats_amount INTEGER NOT NULL, expires_at TEXT NOT NULL, submitted_at TEXT NOT NULL)');
$connection->exec('CREATE TABLE IF NOT EXISTS first_sats_handover_states (invitation_id TEXT PRIMARY KEY, sent_at TEXT NULL, received_at TEXT NULL, not_received_at TEXT NULL, closed_without_confirmation_at TEXT NULL)');
$handoverColumns = array_column($connection->query('PRAGMA table_info(first_sats_handover_states)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('closed_without_confirmation_at', $handoverColumns, true)) {
    $connection->exec('ALTER TABLE first_sats_handover_states ADD COLUMN closed_without_confirmation_at TEXT NULL');
}
$store = new PdoInvitationStore($connection);
$key = str_repeat('preview-key-', 4);
$digester = new SecretDigester($key);
$secrets = new SecretGenerator();
$baseUrl = 'http://127.0.0.1:8891';
$metrics = new PdoMetricsRecorder($connection);
$chfQuotes = new PdoCachedChfQuoteProvider($connection, new StreamKrakenTickerClient(new KrakenTickerParser()));
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add(new SecurityHeadersMiddleware());
(new FirstSatsRoutes(
    new InvitationCreator($store, $secrets, $digester),
    new InvitationRecovery($store, $secrets, $digester),
    new InvitationLifecycle($store, $secrets, $digester),
    new InvitationAccess($store, $digester, new AccessSessionCodec(hash('sha256', 'preview-session', true))),
    new ParticipantJourney($store, $secrets, $digester),
    new LearningJourney($store),
    new WalletJourney($store),
    new Bolt11Inspector(),
    new PdoPaymentRequestStore($connection),
    new PdoHandoverStore($connection),
    new FirstSatsPage($chfQuotes),
    new SameOriginGuard($baseUrl),
    new CsrfToken(hash('sha256', 'preview-csrf', true)),
    $secrets,
    dirname(__DIR__, 2) . '/resources/first-sats',
    $baseUrl,
    false,
    $metrics,
))->register($app);
(new OperatorRoutes(
    new OperatorAccess(
        $digester->digest('first-sats-local-operator-preview-2026'),
        $digester,
        new AccessSessionCodec(hash('sha256', 'preview-operator-session', true)),
    ),
    new PdoDashboardStore($connection),
    new OperatorPage(),
    new SameOriginGuard($baseUrl),
    dirname(__DIR__, 2) . '/resources/first-sats',
    $baseUrl,
    false,
))->register($app);
$app->run();
