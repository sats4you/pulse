<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Payment;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequest;
use Sats4you\Pulse\FirstSats\Payment\PdoPaymentRequestStore;

final class PdoPaymentRequestStoreTest extends TestCase
{
    public function testSavesAndReplacesPaymentRequestWithoutAdditionalParticipantData(): void
    {
        $pdo=new PDO('sqlite::memory:',options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE first_sats_payment_requests (invitation_id TEXT PRIMARY KEY, bolt11_invoice TEXT NOT NULL, sats_amount INTEGER NOT NULL, expires_at TEXT NOT NULL, submitted_at TEXT NOT NULL)');
        $store=new PdoPaymentRequestStore($pdo);
        $store->save(new PaymentRequest('abc','lnbc-first',5000,new DateTimeImmutable('2026-08-23 13:00:00'),new DateTimeImmutable('2026-08-23 12:00:00')));
        $store->save(new PaymentRequest('abc','lnbc-replacement',5000,new DateTimeImmutable('2026-08-23 14:00:00'),new DateTimeImmutable('2026-08-23 12:05:00')));
        $loaded=$store->find('abc');
        self::assertSame('lnbc-replacement',$loaded?->bolt11);
        self::assertSame(5000,$loaded?->satsAmount);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM first_sats_payment_requests')->fetchColumn());
    }
}
