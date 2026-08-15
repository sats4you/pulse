<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Administration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Sats4you\Pulse\Pulse\Event\PublicationState;

final readonly class PdoAdminEventStore implements AdminEventStore
{
    private const DATABASE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private PDO $connection)
    {
    }

    public function listAll(string $roundId): array
    {
        $statement = $this->connection->prepare(
            $this->baseQuery() . <<<'SQL'

                WHERE event.round_id = UNHEX(:round_id)
                ORDER BY event.starts_at DESC, event.created_at DESC
                SQL,
        );
        $statement->execute(['round_id' => self::normaliseId($roundId)]);

        return array_map(self::map(...), $statement->fetchAll());
    }

    public function find(string $roundId, string $publicEventId): ?AdminEvent
    {
        $statement = $this->connection->prepare(
            $this->baseQuery() . <<<'SQL'

                WHERE event.round_id = UNHEX(:round_id)
                  AND event.public_id = UNHEX(:public_id)
                LIMIT 1
                SQL,
        );
        $statement->execute([
            'round_id' => self::normaliseId($roundId),
            'public_id' => self::normaliseId($publicEventId),
        ]);
        $row = $statement->fetch();

        return $row === false ? null : self::map($row);
    }

    public function insert(AdminEvent $event): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                INSERT INTO coordination_events (
                    id, public_id, round_id, title, starts_at, ends_at, location, note,
                    publication_state, publish_at, rsvp_closed_at, material_changed_at,
                    created_at, updated_at, delete_at
                ) VALUES (
                    UNHEX(:id), UNHEX(:public_id), UNHEX(:round_id), :title, :starts_at, :ends_at, :location, :note,
                    :publication_state, :publish_at, :rsvp_closed_at, :material_changed_at,
                    :created_at, :updated_at, :delete_at
                )
                SQL,
        );
        $statement->execute(self::parameters($event));
    }

    public function save(AdminEvent $event): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE coordination_events
                SET title = :title,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    location = :location,
                    note = :note,
                    publication_state = :publication_state,
                    publish_at = :publish_at,
                    rsvp_closed_at = :rsvp_closed_at,
                    material_changed_at = :material_changed_at,
                    updated_at = :updated_at,
                    delete_at = :delete_at
                WHERE id = UNHEX(:id)
                  AND public_id = UNHEX(:public_id)
                  AND round_id = UNHEX(:round_id)
                SQL,
        );
        $parameters = self::parameters($event);
        unset($parameters['created_at']);
        $statement->execute($parameters);
        if ($statement->rowCount() > 1) {
            throw new \UnexpectedValueException('More than one event was updated.');
        }
    }

    private function baseQuery(): string
    {
        return <<<'SQL'
            SELECT
                HEX(event.id) AS id,
                HEX(event.public_id) AS public_id,
                HEX(event.round_id) AS round_id,
                event.title,
                event.starts_at,
                event.ends_at,
                event.location,
                event.note,
                event.publication_state,
                event.publish_at,
                event.rsvp_closed_at,
                event.material_changed_at,
                event.created_at,
                event.updated_at,
                event.delete_at,
                (SELECT COUNT(*) FROM attendance_commitments attendance WHERE attendance.event_id = event.id) AS attendance_count
            FROM coordination_events event
            SQL;
    }

    /** @return array<string, string|null> */
    private static function parameters(AdminEvent $event): array
    {
        return [
            'id' => self::normaliseId($event->id),
            'public_id' => self::normaliseId($event->publicId),
            'round_id' => self::normaliseId($event->roundId),
            'title' => $event->details->title,
            'starts_at' => self::formatDate($event->details->timing->startsAt),
            'ends_at' => self::formatDateOrNull($event->details->timing->endsAt),
            'location' => $event->details->location,
            'note' => $event->details->note,
            'publication_state' => $event->publicationState->value,
            'publish_at' => self::formatDateOrNull($event->publishAt),
            'rsvp_closed_at' => self::formatDateOrNull($event->rsvpClosedAt),
            'material_changed_at' => self::formatDateOrNull($event->materialChangedAt),
            'created_at' => self::formatDate($event->createdAt),
            'updated_at' => self::formatDate($event->updatedAt),
            'delete_at' => self::formatDate($event->deleteAt),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): AdminEvent
    {
        return new AdminEvent(
            strtolower((string) $row['id']),
            strtolower((string) $row['public_id']),
            strtolower((string) $row['round_id']),
            new EventDetails(
                (string) $row['title'],
                self::date((string) $row['starts_at']),
                self::dateOrNull($row['ends_at']),
                $row['location'] === null ? null : (string) $row['location'],
                $row['note'] === null ? null : (string) $row['note'],
            ),
            PublicationState::from((string) $row['publication_state']),
            self::dateOrNull($row['publish_at']),
            self::dateOrNull($row['rsvp_closed_at']),
            self::dateOrNull($row['material_changed_at']),
            self::date((string) $row['created_at']),
            self::date((string) $row['updated_at']),
            self::date((string) $row['delete_at']),
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

    private static function formatDateOrNull(?DateTimeImmutable $value): ?string
    {
        return $value === null ? null : self::formatDate($value);
    }
}
