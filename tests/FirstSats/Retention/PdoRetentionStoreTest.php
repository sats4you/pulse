<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Retention;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Retention\PdoRetentionStore;
use Sats4you\Pulse\FirstSats\Retention\RetentionService;

final class PdoRetentionStoreTest extends TestCase
{
    public function testClosesHandoverDeletesInvoiceAndFinallyDeletesInvitation():void
    {
        $pdo=new PDO('sqlite::memory:',options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec("CREATE TABLE first_sats_invitations (id TEXT PRIMARY KEY,status TEXT NOT NULL,sats_amount INTEGER NOT NULL,accept_until TEXT NOT NULL,complete_until TEXT NULL,stage_four_completed_at TEXT NULL,delete_at TEXT NULL)");
        $pdo->exec("CREATE TABLE first_sats_payment_requests (invitation_id TEXT PRIMARY KEY,bolt11_invoice TEXT,sats_amount INTEGER,expires_at TEXT,submitted_at TEXT,FOREIGN KEY(invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE)");
        $pdo->exec("CREATE TABLE first_sats_handover_states (invitation_id TEXT PRIMARY KEY,sent_at TEXT NULL,received_at TEXT NULL,not_received_at TEXT NULL,closed_without_confirmation_at TEXT NULL,FOREIGN KEY(invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE)");
        $pdo->exec("INSERT INTO first_sats_invitations VALUES ('gift','accepted',5000,'2026-01-31', '2026-01-31','2026-01-01 12:00:00.000000',NULL)");
        $pdo->exec("INSERT INTO first_sats_payment_requests VALUES ('gift','lnbc-test',5000,'2026-01-02','2026-01-01')");

        $service=new RetentionService(new PdoRetentionStore($pdo));
        $closed=$service->run(new DateTimeImmutable('2026-01-15T12:00:01+00:00'));
        self::assertSame(1,$closed->handoversClosed);
        self::assertSame(1,$closed->paymentRequestsDeleted);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM first_sats_payment_requests')->fetchColumn());
        self::assertSame('2026-01-15 12:00:00.000000',$pdo->query('SELECT closed_without_confirmation_at FROM first_sats_handover_states')->fetchColumn());
        self::assertSame('2026-02-14 12:00:00.000000',$pdo->query('SELECT delete_at FROM first_sats_invitations')->fetchColumn());
        self::assertSame(0,$closed->invitationsDeleted);

        $deleted=$service->run(new DateTimeImmutable('2026-02-14T12:00:01+00:00'));
        self::assertSame(1,$deleted->invitationsDeleted);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM first_sats_invitations')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM first_sats_handover_states')->fetchColumn());
    }

    public function testSchedulesReceivedZeroSatsAndExpiredJourneysFromTheirActualEvent():void
    {
        $pdo=new PDO('sqlite::memory:',options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE first_sats_invitations (id TEXT PRIMARY KEY,status TEXT NOT NULL,sats_amount INTEGER NOT NULL,accept_until TEXT NOT NULL,complete_until TEXT NULL,stage_four_completed_at TEXT NULL,delete_at TEXT NULL)");
        $pdo->exec("CREATE TABLE first_sats_payment_requests (invitation_id TEXT PRIMARY KEY)");
        $pdo->exec("CREATE TABLE first_sats_handover_states (invitation_id TEXT PRIMARY KEY,sent_at TEXT NULL,received_at TEXT NULL,not_received_at TEXT NULL,closed_without_confirmation_at TEXT NULL)");
        $pdo->exec("INSERT INTO first_sats_invitations VALUES ('received','accepted',5000,'2026-02-01','2026-02-01','2026-01-01',NULL),('zero','accepted',0,'2026-02-01','2026-02-01','2026-01-02',NULL),('open-expired','open',5000,'2026-01-03',NULL,NULL,NULL),('learning-expired','accepted',5000,'2026-01-01','2026-01-04',NULL,NULL)");
        $pdo->exec("INSERT INTO first_sats_handover_states VALUES ('received',NULL,'2026-01-01 10:00:00',NULL,NULL)");
        $report=(new RetentionService(new PdoRetentionStore($pdo)))->run(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));
        self::assertSame(4,$report->invitationsScheduled);
        $dates=$pdo->query('SELECT id,delete_at FROM first_sats_invitations ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertStringStartsWith('2026-02-03',$dates['learning-expired']);
        self::assertStringStartsWith('2026-02-02',$dates['open-expired']);
        self::assertStringStartsWith('2026-01-31',$dates['received']);
        self::assertStringStartsWith('2026-02-01',$dates['zero']);
    }
}
