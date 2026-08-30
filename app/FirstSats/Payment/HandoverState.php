<?php
declare(strict_types=1);
namespace Sats4you\Pulse\FirstSats\Payment;
use DateTimeImmutable;
final readonly class HandoverState { public function __construct(public string $invitationId,public ?DateTimeImmutable $sentAt=null,public ?DateTimeImmutable $receivedAt=null,public ?DateTimeImmutable $notReceivedAt=null,public ?DateTimeImmutable $closedWithoutConfirmationAt=null){} }
