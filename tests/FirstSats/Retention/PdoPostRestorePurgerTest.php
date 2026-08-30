<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Retention;

use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Retention\PdoPostRestorePurger;

final class PdoPostRestorePurgerTest extends TestCase
{
    public function testDeletesAllInvitationLinkedDataAndPreservesAnonymousCounters(): void
    {
        $connection = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('CREATE TABLE first_sats_invitations (id TEXT PRIMARY KEY)');
        $connection->exec('CREATE TABLE first_sats_payment_requests (invitation_id TEXT PRIMARY KEY REFERENCES first_sats_invitations(id) ON DELETE CASCADE)');
        $connection->exec('CREATE TABLE first_sats_handover_states (invitation_id TEXT PRIMARY KEY REFERENCES first_sats_invitations(id) ON DELETE CASCADE)');
        $connection->exec('CREATE TABLE first_sats_metric_contributions (invitation_id TEXT REFERENCES first_sats_invitations(id) ON DELETE CASCADE)');
        $connection->exec('CREATE TABLE first_sats_anonymous_counters (metric_key TEXT PRIMARY KEY, count_value INTEGER NOT NULL)');
        $connection->exec("INSERT INTO first_sats_invitations VALUES ('one'), ('two')");
        $connection->exec("INSERT INTO first_sats_payment_requests VALUES ('one')");
        $connection->exec("INSERT INTO first_sats_handover_states VALUES ('two')");
        $connection->exec("INSERT INTO first_sats_metric_contributions VALUES ('one')");
        $connection->exec("INSERT INTO first_sats_anonymous_counters VALUES ('journey_completed', 7)");

        $deleted = (new PdoPostRestorePurger($connection))->purgeInvitationLinkedData();

        self::assertSame(2, $deleted);
        foreach (['first_sats_invitations', 'first_sats_payment_requests', 'first_sats_handover_states', 'first_sats_metric_contributions'] as $table) {
            self::assertSame(0, (int) $connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn());
        }
        self::assertSame(7, (int) $connection->query('SELECT count_value FROM first_sats_anonymous_counters')->fetchColumn());
    }
}
