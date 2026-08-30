<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Payment;

use DateTimeImmutable;
use PDO;

final readonly class PdoPaymentRequestStore implements PaymentRequestStore
{
    public function __construct(private PDO $connection) {}

    public function find(string $invitationId): ?PaymentRequest
    {
        $s = $this->connection->prepare('SELECT * FROM first_sats_payment_requests WHERE invitation_id = :id');
        $s->execute(['id' => $invitationId]); $r = $s->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? new PaymentRequest((string)$r['invitation_id'], (string)$r['bolt11_invoice'], (int)$r['sats_amount'], new DateTimeImmutable((string)$r['expires_at']), new DateTimeImmutable((string)$r['submitted_at'])) : null;
    }

    public function save(PaymentRequest $request): void
    {
        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = <<<'SQL'
                INSERT INTO first_sats_payment_requests (invitation_id, bolt11_invoice, sats_amount, expires_at, submitted_at)
                VALUES (:id, :invoice, :amount, :expires, :submitted)
                ON CONFLICT(invitation_id) DO UPDATE SET bolt11_invoice=excluded.bolt11_invoice, sats_amount=excluded.sats_amount, expires_at=excluded.expires_at, submitted_at=excluded.submitted_at
                SQL;
        } else {
            $sql = <<<'SQL'
                INSERT INTO first_sats_payment_requests (invitation_id, bolt11_invoice, sats_amount, expires_at, submitted_at)
                VALUES (:id, :invoice, :amount, :expires, :submitted)
                ON DUPLICATE KEY UPDATE bolt11_invoice = VALUES(bolt11_invoice), sats_amount = VALUES(sats_amount), expires_at = VALUES(expires_at), submitted_at = VALUES(submitted_at)
                SQL;
        }
        $s = $this->connection->prepare($sql);
        $s->execute(['id'=>$request->invitationId,'invoice'=>$request->bolt11,'amount'=>$request->satsAmount,'expires'=>$request->expiresAt->format('Y-m-d H:i:s.u'),'submitted'=>$request->submittedAt->format('Y-m-d H:i:s.u')]);
    }
}
