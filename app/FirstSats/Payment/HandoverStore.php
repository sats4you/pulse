<?php
declare(strict_types=1);
namespace Sats4you\Pulse\FirstSats\Payment;
use DateTimeImmutable;
interface HandoverStore { public function find(string $invitationId): ?HandoverState; public function markSent(string $invitationId,DateTimeImmutable $at): HandoverState; public function markNotReceived(string $invitationId,DateTimeImmutable $at): HandoverState; public function confirmReceived(string $invitationId,DateTimeImmutable $at): HandoverState; }
