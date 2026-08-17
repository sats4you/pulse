<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\Http\PulseApplicationFactory;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Administration\AdminBootstrapPage;
use Sats4you\Pulse\Pulse\Administration\AdminEventService;
use Sats4you\Pulse\Pulse\Administration\AdminPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialRotationService;
use Sats4you\Pulse\Pulse\Credentials\RecoveryBootstrapPage;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantFlow;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantPage;
use Sats4you\Pulse\Pulse\PublicAccess\PulseLandingPage;
use Sats4you\Pulse\Pulse\Privacy\PrivacyPage;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\SqlitePulseStore;

$projectRoot = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/assets/') && is_file($projectRoot . '/public' . $path)) {
    return false;
}

require $projectRoot . '/vendor/autoload.php';

$databasePath = $projectRoot . '/var/browser-fixture.sqlite';
if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0770, true);
}
$connection = new PDO('sqlite:' . $databasePath, options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$connection->exec('CREATE TABLE IF NOT EXISTS rounds (id TEXT PRIMARY KEY, slug TEXT UNIQUE, participant_digest BLOB, access_version INTEGER, admin_digest BLOB, admin_version INTEGER, recovery_digest BLOB, recovery_version INTEGER)');
$roundColumns = array_column($connection->query('PRAGMA table_info(rounds)')->fetchAll(), 'name');
if (!in_array('admin_digest', $roundColumns, true)) {
    $connection->exec('ALTER TABLE rounds ADD COLUMN admin_digest BLOB');
}
if (!in_array('admin_version', $roundColumns, true)) {
    $connection->exec('ALTER TABLE rounds ADD COLUMN admin_version INTEGER NOT NULL DEFAULT 1');
}
if (!in_array('recovery_digest', $roundColumns, true)) {
    $connection->exec('ALTER TABLE rounds ADD COLUMN recovery_digest BLOB');
}
if (!in_array('recovery_version', $roundColumns, true)) {
    $connection->exec('ALTER TABLE rounds ADD COLUMN recovery_version INTEGER NOT NULL DEFAULT 1');
}
$connection->exec('CREATE TABLE IF NOT EXISTS events (id TEXT PRIMARY KEY, public_id TEXT UNIQUE, round_id TEXT, title TEXT, starts_at TEXT, ends_at TEXT, location TEXT, note TEXT, publication_state TEXT, publish_at TEXT, rsvp_closed_at TEXT, material_changed_at TEXT, created_at TEXT, updated_at TEXT, delete_at TEXT)');
$eventColumns = array_column($connection->query('PRAGMA table_info(events)')->fetchAll(), 'name');
foreach (['created_at', 'updated_at', 'delete_at'] as $column) {
    if (!in_array($column, $eventColumns, true)) {
        $connection->exec('ALTER TABLE events ADD COLUMN ' . $column . ' TEXT');
    }
}
$connection->exec('CREATE TABLE IF NOT EXISTS attendance (event_id TEXT, digest BLOB, created_at TEXT, delete_at TEXT, UNIQUE(event_id, digest))');
$connection->exec('CREATE TABLE IF NOT EXISTS notification_changes (id TEXT PRIMARY KEY, event_id TEXT, change_type TEXT, occurred_at TEXT, delete_at TEXT)');

$digester = new SecretDigester(str_repeat('h', 32));
if ((int) $connection->query('SELECT COUNT(*) FROM rounds')->fetchColumn() === 0) {
    $roundId = '0123456789abcdef0123456789abcdef';
    $eventId = '11111111111111111111111111111111';
    $publicId = '22222222222222222222222222222222';
    $now = new DateTimeImmutable();
    $start = $now->modify('+12 days')->setTime(16, 30);
    $round = $connection->prepare('INSERT INTO rounds (id, slug, participant_digest, access_version, admin_digest, admin_version, recovery_digest, recovery_version) VALUES (:id, :slug, :digest, 1, :admin_digest, 1, :recovery_digest, 1)');
    $round->bindValue('id', $roundId);
    $round->bindValue('slug', 'bern-bitcoin');
    $round->bindValue('digest', $digester->digest('test-participant-secret'), PDO::PARAM_LOB);
    $round->bindValue('admin_digest', $digester->digest('test-admin-secret'), PDO::PARAM_LOB);
    $round->bindValue('recovery_digest', $digester->digest('test-recovery-secret'), PDO::PARAM_LOB);
    $round->execute();
    $event = $connection->prepare('INSERT INTO events (id, public_id, round_id, title, starts_at, ends_at, location, note, publication_state, publish_at, rsvp_closed_at, material_changed_at, created_at, updated_at, delete_at) VALUES (:id, :public_id, :round_id, :title, :starts_at, NULL, NULL, :note, :state, :publish_at, NULL, NULL, :created_at, :updated_at, :delete_at)');
    $event->execute([
        'id' => $eventId,
        'public_id' => $publicId,
        'round_id' => $roundId,
        'title' => 'Bern Monthly Bitcoin Meetup',
        'starts_at' => $start->format('Y-m-d H:i:s.u'),
        'note' => 'Geschlossener Pulse-Pilot.',
        'state' => 'published',
        'publish_at' => $now->modify('-1 day')->format('Y-m-d H:i:s.u'),
        'created_at' => $now->format('Y-m-d H:i:s.u'),
        'updated_at' => $now->format('Y-m-d H:i:s.u'),
        'delete_at' => $start->modify('+30 days +6 hours')->format('Y-m-d H:i:s.u'),
    ]);
    $attendance = $connection->prepare('INSERT INTO attendance VALUES (:event_id, :digest, :created_at, :delete_at)');
    foreach (range(1, 4) as $index) {
        $attendance->bindValue('event_id', $eventId);
        $attendance->bindValue('digest', $digester->digest('fixture-' . $index), PDO::PARAM_LOB);
        $attendance->bindValue('created_at', $now->format('Y-m-d H:i:s.u'));
        $attendance->bindValue('delete_at', $start->modify('+7 days +6 hours')->format('Y-m-d H:i:s.u'));
        $attendance->execute();
    }
}
$fixtureNow = new DateTimeImmutable();
$connection->prepare('UPDATE events SET created_at = COALESCE(created_at, :now), updated_at = COALESCE(updated_at, :now), delete_at = COALESCE(delete_at, :delete_at)')->execute([
    'now' => $fixtureNow->format('Y-m-d H:i:s.u'),
    'delete_at' => $fixtureNow->modify('+60 days')->format('Y-m-d H:i:s.u'),
]);

$store = new SqlitePulseStore($connection);
$policy = new EventAccessPolicy();
$attendanceService = new AttendanceService(
    $store,
    $policy,
    new RetentionSchedule(),
    new SecretGenerator(),
    $digester,
);
$application = new PulseApplicationFactory(
    new AccessExchange($store, $digester, new AccessSessionCodec(str_repeat('s', 32))),
    new ParticipantFlow($store, $store, $attendanceService, $digester),
    new AdminEventService($store, new RetentionSchedule()),
    new CredentialRotationService($store, new SecretGenerator(), $digester),
    new BootstrapPage(),
    new ParticipantPage($policy),
    new PulseLandingPage(),
    new AdminBootstrapPage(),
    new AdminPage(),
    new RecoveryBootstrapPage(),
    new CredentialPage(),
    new PrivacyPage(),
    new SameOriginGuard('http://127.0.0.1:' . $_SERVER['SERVER_PORT']),
    new CsrfToken(str_repeat('c', 32)),
    $projectRoot . '/resources/translations',
    'Bern Monthly Bitcoin Meetup',
    'http://127.0.0.1:' . $_SERVER['SERVER_PORT'],
    false,
);
$application->create()->run();
