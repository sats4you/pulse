<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class PdoNotificationOutboxStore implements NotificationOutboxStore
{
    private const DATABASE_FORMAT = 'Y-m-d H:i:s.u';
    private const LOCK_NAME = 'sats4you_pulse_notification_dispatch';

    public function __construct(private PDO $connection)
    {
    }

    public function acquireDispatchLock(): bool
    {
        $statement = $this->connection->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => self::LOCK_NAME]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function releaseDispatchLock(): void
    {
        $statement = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => self::LOCK_NAME]);
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        $statement = $this->connection->prepare(
            'DELETE FROM attendance_notification_changes WHERE delete_at <= :now',
        );
        $statement->execute([
            'now' => $now->setTimezone(new DateTimeZone('UTC'))->format(self::DATABASE_FORMAT),
        ]);

        return $statement->rowCount();
    }

    public function pendingBatches(DateTimeImmutable $readyBefore): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    LOWER(HEX(notification.id)) AS change_id,
                    LOWER(HEX(event.id)) AS event_id,
                    event.title,
                    event.starts_at,
                    notification.change_type,
                    (
                        SELECT COUNT(*)
                        FROM attendance_commitments attendance
                        WHERE attendance.event_id = event.id
                    ) AS current_count
                FROM attendance_notification_changes notification
                INNER JOIN coordination_events event ON event.id = notification.event_id
                WHERE notification.event_id IN (
                    SELECT eligible.event_id
                    FROM (
                        SELECT event_id
                        FROM attendance_notification_changes
                        GROUP BY event_id
                        HAVING MAX(occurred_at) <= :ready_before
                    ) eligible
                )
                ORDER BY event.starts_at ASC, event.id ASC, notification.occurred_at ASC, notification.id ASC
                SQL,
        );
        $statement->execute([
            'ready_before' => $readyBefore->setTimezone(new DateTimeZone('UTC'))->format(self::DATABASE_FORMAT),
        ]);

        /** @var array<string, array{eventId: string, eventTitle: string, startsAt: DateTimeImmutable, joins: int, withdrawals: int, currentCount: int, changeIds: list<string>}> $grouped */
        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $eventId = (string) $row['event_id'];
            if (!isset($grouped[$eventId])) {
                $grouped[$eventId] = [
                    'eventId' => $eventId,
                    'eventTitle' => (string) $row['title'],
                    'startsAt' => self::date((string) $row['starts_at']),
                    'joins' => 0,
                    'withdrawals' => 0,
                    'currentCount' => (int) $row['current_count'],
                    'changeIds' => [],
                ];
            }

            if ($row['change_type'] === 'join') {
                ++$grouped[$eventId]['joins'];
            } elseif ($row['change_type'] === 'withdraw') {
                ++$grouped[$eventId]['withdrawals'];
            }
            $grouped[$eventId]['changeIds'][] = (string) $row['change_id'];
        }

        return array_map(
            static fn (array $batch): NotificationBatch => new NotificationBatch(...$batch),
            array_values($grouped),
        );
    }

    public function deleteChanges(array $changeIds): void
    {
        if ($changeIds === []) {
            return;
        }

        $normalised = array_map(self::normaliseId(...), $changeIds);
        $placeholders = implode(', ', array_fill(0, count($normalised), 'UNHEX(?)'));
        $statement = $this->connection->prepare(
            'DELETE FROM attendance_notification_changes WHERE id IN (' . $placeholders . ')',
        );
        $statement->execute($normalised);
    }

    private static function normaliseId(string $id): string
    {
        $normalised = strtolower(str_replace('-', '', $id));
        if (preg_match('/^[a-f0-9]{32}$/', $normalised) !== 1) {
            throw new \InvalidArgumentException('Notification identifier must contain 32 hexadecimal characters.');
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
}
