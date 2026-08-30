<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Payment;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Payment\PdoHandoverStore;

final class PdoHandoverStoreTest extends TestCase
{
    public function testStoresSeparateConfirmationsAndDeletesInvoiceOnReceipt(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE first_sats_payment_requests (invitation_id TEXT PRIMARY KEY, bolt11_invoice TEXT NOT NULL, sats_amount INTEGER NOT NULL, expires_at TEXT NOT NULL, submitted_at TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE first_sats_handover_states (invitation_id TEXT PRIMARY KEY, sent_at TEXT NULL, received_at TEXT NULL, not_received_at TEXT NULL)');
        $pdo->exec("INSERT INTO first_sats_payment_requests VALUES ('abc', 'lnbc-test', 5000, '2026-08-23 14:00:00', '2026-08-23 12:00:00')");

        $store = new PdoHandoverStore($pdo);
        $sent = $store->markSent('abc', new DateTimeImmutable('2026-08-23 12:01:00'));
        self::assertNotNull($sent->sentAt);
        self::assertNull($sent->receivedAt);

        $waiting = $store->markNotReceived('abc', new DateTimeImmutable('2026-08-23 12:02:00'));
        self::assertNotNull($waiting->notReceivedAt);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM first_sats_payment_requests')->fetchColumn());

        $received = $store->confirmReceived('abc', new DateTimeImmutable('2026-08-23 12:03:00'));
        self::assertNotNull($received->sentAt);
        self::assertNotNull($received->receivedAt);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM first_sats_payment_requests')->fetchColumn());
    }
}
