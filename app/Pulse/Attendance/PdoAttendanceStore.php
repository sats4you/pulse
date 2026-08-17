<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Attendance;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class PdoAttendanceStore implements AttendanceStore
{
    private const DATABASE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private PDO $connection)
    {
    }

    public function transaction(callable $operation): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $error;
        }
    }

    public function getEventForUpdate(string $roundId, string $eventId): ?EventSnapshot
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    HEX(id) AS id,
                    HEX(round_id) AS round_id,
                    publication_state,
                    publish_at,
                    starts_at,
                    ends_at,
                    rsvp_closed_at
                FROM coordination_events
                WHERE id = UNHEX(:event_id)
                  AND round_id = UNHEX(:round_id)
                FOR UPDATE
                SQL,
        );
        $statement->execute([
            'event_id' => self::normaliseId($eventId),
            'round_id' => self::normaliseId($roundId),
        ]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new EventSnapshot(
            strtolower($row['id']),
            strtolower($row['round_id']),
            PublicationState::from($row['publication_state']),
            self::dateOrNull($row['publish_at']),
            new EventTiming(
                self::date($row['starts_at']),
                self::dateOrNull($row['ends_at']),
            ),
            self::dateOrNull($row['rsvp_closed_at']),
        );
    }

    public function hasCommitment(string $eventId, string $secretDigest): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM attendance_commitments WHERE event_id = UNHEX(:event_id) AND participant_secret_digest = :digest',
        );
        $statement->bindValue('event_id', self::normaliseId($eventId));
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function insertCommitment(
        string $eventId,
        string $secretDigest,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deleteAt,
    ): void {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO attendance_commitments
                    (id, event_id, participant_secret_digest, created_at, delete_at)
                VALUES
                    (:id, UNHEX(:event_id), :digest, :created_at, :delete_at)
                SQL,
        );
        $statement->bindValue('id', random_bytes(16), PDO::PARAM_LOB);
        $statement->bindValue('event_id', self::normaliseId($eventId));
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->bindValue('created_at', self::formatDate($createdAt));
        $statement->bindValue('delete_at', self::formatDate($deleteAt));
        $statement->execute();
    }

    public function deleteCommitment(string $eventId, string $secretDigest): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM attendance_commitments WHERE event_id = UNHEX(:event_id) AND participant_secret_digest = :digest',
        );
        $statement->bindValue('event_id', self::normaliseId($eventId));
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function countCommitments(string $eventId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM attendance_commitments WHERE event_id = UNHEX(:event_id)',
        );
        $statement->execute(['event_id' => self::normaliseId($eventId)]);

        return (int) $statement->fetchColumn();
    }

    public function recordNotificationChange(
        string $eventId,
        string $changeType,
        DateTimeImmutable $occurredAt,
    ): void {
        if (!in_array($changeType, ['join', 'withdraw'], true)) {
            throw new \InvalidArgumentException('Unsupported attendance notification change type.');
        }

        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO attendance_notification_changes
                    (id, event_id, change_type, occurred_at, delete_at)
                VALUES
                    (:id, UNHEX(:event_id), :change_type, :occurred_at, :delete_at)
                SQL,
        );
        $statement->bindValue('id', random_bytes(16), PDO::PARAM_LOB);
        $statement->bindValue('event_id', self::normaliseId($eventId));
        $statement->bindValue('change_type', $changeType);
        $statement->bindValue('occurred_at', self::formatDate($occurredAt));
        $statement->bindValue('delete_at', self::formatDate($occurredAt->modify('+7 days')));
        $statement->execute();
    }

    private static function normaliseId(string $id): string
    {
        $normalised = strtolower(str_replace('-', '', $id));
        if (preg_match('/^[a-f0-9]{32}$/', $normalised) !== 1) {
            throw new \InvalidArgumentException('Identifier must be a UUID or 32 hexadecimal characters.');
        }

        return $normalised;
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            self::DATABASE_FORMAT,
            $value,
            new DateTimeZone('UTC'),
        );
        if ($date === false) {
            throw new \UnexpectedValueException('Database returned an invalid UTC date.');
        }

        return $date;
    }

    private static function dateOrNull(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::date($value);
    }

    private static function formatDate(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::DATABASE_FORMAT);
    }
}
