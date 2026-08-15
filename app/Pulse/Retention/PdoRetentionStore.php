<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Retention;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class PdoRetentionStore implements RetentionStore
{
    public function __construct(private PDO $connection)
    {
    }

    public function deleteDueAttendance(DateTimeImmutable $now): int
    {
        $statement = $this->connection->prepare('DELETE FROM attendance_commitments WHERE delete_at <= :now');
        $statement->execute(['now' => self::format($now)]);

        return $statement->rowCount();
    }

    public function deleteDueEvents(DateTimeImmutable $now): int
    {
        $statement = $this->connection->prepare('DELETE FROM coordination_events WHERE delete_at <= :now');
        $statement->execute(['now' => self::format($now)]);

        return $statement->rowCount();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
