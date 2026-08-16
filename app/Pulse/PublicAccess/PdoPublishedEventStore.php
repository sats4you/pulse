<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class PdoPublishedEventStore implements PublishedEventStore
{
    private const DATABASE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private PDO $connection)
    {
    }

    public function listUpcoming(string $roundId, DateTimeImmutable $now): array
    {
        $statement = $this->connection->prepare(
            $this->baseQuery() . <<<'SQL'

                WHERE event.round_id = UNHEX(:round_id)
                  AND (
                    event.publication_state = 'published'
                    OR (event.publication_state IN ('scheduled', 'cancelled') AND event.publish_at <= :publish_now)
                  )
                  AND COALESCE(event.ends_at, DATE_ADD(event.starts_at, INTERVAL 6 HOUR)) >= :active_now
                ORDER BY event.starts_at ASC, event.created_at ASC
                SQL,
        );
        $statement->execute([
            'round_id' => self::normaliseId($roundId),
            'publish_now' => self::formatDate($now),
            'active_now' => self::formatDate($now),
        ]);

        return array_map(self::map(...), $statement->fetchAll());
    }

    public function findUpcoming(string $roundId, string $publicEventId, DateTimeImmutable $now): ?PublishedEvent
    {
        $statement = $this->connection->prepare(
            $this->baseQuery() . <<<'SQL'

                WHERE event.round_id = UNHEX(:round_id)
                  AND event.public_id = UNHEX(:public_id)
                  AND (
                    event.publication_state = 'published'
                    OR (event.publication_state IN ('scheduled', 'cancelled') AND event.publish_at <= :publish_now)
                  )
                  AND COALESCE(event.ends_at, DATE_ADD(event.starts_at, INTERVAL 6 HOUR)) >= :active_now
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'round_id' => self::normaliseId($roundId),
            'public_id' => self::normaliseId($publicEventId),
            'publish_now' => self::formatDate($now),
            'active_now' => self::formatDate($now),
        ]);
        $row = $statement->fetch();

        return $row === false ? null : self::map($row);
    }

    private function baseQuery(): string
    {
        return <<<'SQL'
            SELECT
                HEX(event.id) AS id,
                HEX(event.public_id) AS public_id,
                event.title,
                event.starts_at,
                event.ends_at,
                event.location,
                event.note,
                event.publication_state,
                event.publish_at,
                event.rsvp_closed_at,
                event.material_changed_at,
                (SELECT COUNT(*) FROM attendance_commitments attendance WHERE attendance.event_id = event.id) AS attendance_count
            FROM coordination_events event
            SQL;
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): PublishedEvent
    {
        return new PublishedEvent(
            strtolower((string) $row['id']),
            strtolower((string) $row['public_id']),
            (string) $row['title'],
            new EventTiming(self::date($row['starts_at']), self::dateOrNull($row['ends_at'])),
            $row['location'] === null ? null : (string) $row['location'],
            $row['note'] === null ? null : (string) $row['note'],
            PublicationState::from((string) $row['publication_state']),
            self::dateOrNull($row['publish_at']),
            self::dateOrNull($row['rsvp_closed_at']),
            self::dateOrNull($row['material_changed_at']),
            (int) $row['attendance_count'],
        );
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
        $date = DateTimeImmutable::createFromFormat(self::DATABASE_FORMAT, $value, new DateTimeZone('UTC'));
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
