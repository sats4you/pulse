<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Operations;

use DateTimeImmutable;
use PDO;

final readonly class PdoOperationalHeartbeatStore
{
    public function __construct(private PDO $connection)
    {
    }

    public function recordSuccess(string $operation, DateTimeImmutable $at): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $operation) !== 1) {
            throw new \InvalidArgumentException('Invalid operation key.');
        }

        $sql = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO operational_heartbeats (operation_key, last_succeeded_at) VALUES (:operation, :at)
               ON CONFLICT(operation_key) DO UPDATE SET last_succeeded_at = excluded.last_succeeded_at'
            : 'INSERT INTO operational_heartbeats (operation_key, last_succeeded_at) VALUES (:operation, :at)
               ON DUPLICATE KEY UPDATE last_succeeded_at = VALUES(last_succeeded_at)';
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'operation' => $operation,
            'at' => $at->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function lastSuccess(string $operation): ?DateTimeImmutable
    {
        $statement = $this->connection->prepare(
            'SELECT last_succeeded_at FROM operational_heartbeats WHERE operation_key = :operation'
        );
        $statement->execute(['operation' => $operation]);
        $value = $statement->fetchColumn();

        return is_string($value) ? new DateTimeImmutable($value) : null;
    }
}
