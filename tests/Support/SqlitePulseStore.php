<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Sats4you\Pulse\Pulse\Attendance\AttendanceStore;
use Sats4you\Pulse\Pulse\Attendance\EventSnapshot;
use Sats4you\Pulse\Pulse\Administration\AdminEvent;
use Sats4you\Pulse\Pulse\Administration\AdminEventStore;
use Sats4you\Pulse\Pulse\Administration\EventDetails;
use Sats4you\Pulse\Pulse\Credentials\CredentialStore;
use Sats4you\Pulse\Pulse\Credentials\RoundCredentialSnapshot;
use Sats4you\Pulse\Pulse\Event\EventTiming;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Pulse\PublicAccess\PublishedEvent;
use Sats4you\Pulse\Pulse\PublicAccess\PublishedEventStore;
use Sats4you\Pulse\Pulse\PublicAccess\RoundAccessStore;
use Throwable;

final readonly class SqlitePulseStore implements AttendanceStore, PublishedEventStore, RoundAccessStore, AdminEventStore, CredentialStore
{
    private const FORMAT = 'Y-m-d H:i:s.u';

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
        $statement = $this->connection->prepare('SELECT * FROM events WHERE id = :id AND round_id = :round_id');
        $statement->execute(['id' => $eventId, 'round_id' => $roundId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new EventSnapshot(
            $row['id'],
            $row['round_id'],
            PublicationState::from($row['publication_state']),
            self::dateOrNull($row['publish_at']),
            new EventTiming(self::date($row['starts_at']), self::dateOrNull($row['ends_at'])),
            self::dateOrNull($row['rsvp_closed_at']),
        );
    }

    public function hasCommitment(string $eventId, string $secretDigest): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM attendance WHERE event_id = :event_id AND digest = :digest');
        $statement->bindValue('event_id', $eventId);
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    public function insertCommitment(string $eventId, string $secretDigest, DateTimeImmutable $createdAt, DateTimeImmutable $deleteAt): void
    {
        $statement = $this->connection->prepare('INSERT INTO attendance (event_id, digest, created_at, delete_at) VALUES (:event_id, :digest, :created_at, :delete_at)');
        $statement->bindValue('event_id', $eventId);
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->bindValue('created_at', self::format($createdAt));
        $statement->bindValue('delete_at', self::format($deleteAt));
        $statement->execute();
    }

    public function deleteCommitment(string $eventId, string $secretDigest): bool
    {
        $statement = $this->connection->prepare('DELETE FROM attendance WHERE event_id = :event_id AND digest = :digest');
        $statement->bindValue('event_id', $eventId);
        $statement->bindValue('digest', $secretDigest, PDO::PARAM_LOB);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function countCommitments(string $eventId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM attendance WHERE event_id = :event_id');
        $statement->execute(['event_id' => $eventId]);

        return (int) $statement->fetchColumn();
    }

    public function listUpcoming(string $roundId, DateTimeImmutable $now): array
    {
        $statement = $this->connection->prepare(
            "SELECT event.*, (SELECT COUNT(*) FROM attendance WHERE event_id = event.id) attendance_count
             FROM events event
             WHERE event.round_id = :round_id
               AND (event.publication_state = 'published' OR (event.publication_state IN ('scheduled','cancelled') AND event.publish_at <= :now))
               AND COALESCE(event.ends_at, datetime(event.starts_at, '+6 hours')) >= :now
             ORDER BY event.starts_at ASC",
        );
        $statement->execute(['round_id' => $roundId, 'now' => self::format($now)]);

        return array_map(self::published(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findUpcoming(string $roundId, string $publicEventId, DateTimeImmutable $now): ?PublishedEvent
    {
        foreach ($this->listUpcoming($roundId, $now) as $event) {
            if ($event->publicId === $publicEventId) {
                return $event;
            }
        }

        return null;
    }

    public function listAll(string $roundId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event.*, (SELECT COUNT(*) FROM attendance WHERE event_id = event.id) attendance_count FROM events event WHERE event.round_id = :round_id ORDER BY event.starts_at DESC, event.created_at DESC',
        );
        $statement->execute(['round_id' => $roundId]);

        return array_map(self::adminEvent(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(string $roundId, string $publicEventId): ?AdminEvent
    {
        $statement = $this->connection->prepare(
            'SELECT event.*, (SELECT COUNT(*) FROM attendance WHERE event_id = event.id) attendance_count FROM events event WHERE event.round_id = :round_id AND event.public_id = :public_id LIMIT 1',
        );
        $statement->execute(['round_id' => $roundId, 'public_id' => $publicEventId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::adminEvent($row);
    }

    public function insert(AdminEvent $event): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO events (id, public_id, round_id, title, starts_at, ends_at, location, note, publication_state, publish_at, rsvp_closed_at, material_changed_at, created_at, updated_at, delete_at) VALUES (:id, :public_id, :round_id, :title, :starts_at, :ends_at, :location, :note, :publication_state, :publish_at, :rsvp_closed_at, :material_changed_at, :created_at, :updated_at, :delete_at)',
        );
        $statement->execute(self::adminParameters($event));
    }

    public function save(AdminEvent $event): void
    {
        $statement = $this->connection->prepare(
            'UPDATE events SET title = :title, starts_at = :starts_at, ends_at = :ends_at, location = :location, note = :note, publication_state = :publication_state, publish_at = :publish_at, rsvp_closed_at = :rsvp_closed_at, material_changed_at = :material_changed_at, updated_at = :updated_at, delete_at = :delete_at WHERE id = :id AND public_id = :public_id AND round_id = :round_id',
        );
        $parameters = self::adminParameters($event);
        unset($parameters['created_at']);
        $statement->execute($parameters);
    }

    public function delete(string $roundId, string $publicEventId): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM events WHERE round_id = :round_id AND public_id = :public_id',
        );
        $statement->execute(['round_id' => $roundId, 'public_id' => $publicEventId]);

        return $statement->rowCount() === 1;
    }

    public function findParticipantGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        $statement = $this->connection->prepare('SELECT * FROM rounds WHERE slug = :slug');
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !hash_equals($row['participant_digest'], $presentedDigest)) {
            return null;
        }

        return new AccessGrant($row['id'], AccessRole::Participant, (int) $row['access_version']);
    }

    public function findAdministratorGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        $statement = $this->connection->prepare('SELECT * FROM rounds WHERE slug = :slug');
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false
            || !array_key_exists('admin_digest', $row)
            || !hash_equals((string) $row['admin_digest'], $presentedDigest)
        ) {
            return null;
        }

        return new AccessGrant($row['id'], AccessRole::Administrator, (int) $row['admin_version']);
    }

    public function findRecoveryGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        $statement = $this->connection->prepare('SELECT * FROM rounds WHERE slug = :slug');
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false
            || !array_key_exists('recovery_digest', $row)
            || !hash_equals((string) $row['recovery_digest'], $presentedDigest)
        ) {
            return null;
        }

        return new AccessGrant($row['id'], AccessRole::Recovery, (int) $row['recovery_version']);
    }

    public function isCurrent(AccessGrant $grant, string $publicSlug): bool
    {
        $versionColumn = match ($grant->role) {
            AccessRole::Participant => 'access_version',
            AccessRole::Administrator => 'admin_version',
            AccessRole::Recovery => 'recovery_version',
        };
        $statement = $this->connection->prepare("SELECT 1 FROM rounds WHERE id = :id AND slug = :slug AND {$versionColumn} = :version");
        $statement->execute(['id' => $grant->roundId, 'slug' => $publicSlug, 'version' => $grant->accessVersion]);

        return $statement->fetchColumn() !== false;
    }

    public function getRoundForUpdate(string $publicSlug): ?RoundCredentialSnapshot
    {
        $statement = $this->connection->prepare('SELECT * FROM rounds WHERE slug = :slug');
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new RoundCredentialSnapshot(
            (string) $row['id'],
            (string) $row['slug'],
            (int) $row['access_version'],
            (int) $row['admin_version'],
            (int) $row['recovery_version'],
        );
    }

    public function replaceAdministratorCredentials(
        RoundCredentialSnapshot $expected,
        string $administratorDigest,
        string $recoveryDigest,
        DateTimeImmutable $rotatedAt,
    ): bool {
        $statement = $this->connection->prepare(
            'UPDATE rounds SET admin_digest = :admin_digest, admin_version = admin_version + 1, recovery_digest = :recovery_digest, recovery_version = recovery_version + 1 WHERE id = :id AND slug = :slug AND admin_version = :admin_version AND recovery_version = :recovery_version',
        );
        $statement->bindValue('admin_digest', $administratorDigest, PDO::PARAM_LOB);
        $statement->bindValue('recovery_digest', $recoveryDigest, PDO::PARAM_LOB);
        $statement->bindValue('id', $expected->roundId);
        $statement->bindValue('slug', $expected->publicSlug);
        $statement->bindValue('admin_version', $expected->administratorVersion, PDO::PARAM_INT);
        $statement->bindValue('recovery_version', $expected->recoveryVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function replaceParticipantCredential(
        RoundCredentialSnapshot $expected,
        string $participantDigest,
        DateTimeImmutable $rotatedAt,
    ): bool {
        $statement = $this->connection->prepare(
            'UPDATE rounds SET participant_digest = :participant_digest, access_version = access_version + 1 WHERE id = :id AND slug = :slug AND access_version = :participant_version AND admin_version = :admin_version',
        );
        $statement->bindValue('participant_digest', $participantDigest, PDO::PARAM_LOB);
        $statement->bindValue('id', $expected->roundId);
        $statement->bindValue('slug', $expected->publicSlug);
        $statement->bindValue('participant_version', $expected->participantVersion, PDO::PARAM_INT);
        $statement->bindValue('admin_version', $expected->administratorVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    private static function published(array $row): PublishedEvent
    {
        return new PublishedEvent(
            $row['id'],
            $row['public_id'],
            $row['title'],
            new EventTiming(self::date($row['starts_at']), self::dateOrNull($row['ends_at'])),
            $row['location'],
            $row['note'],
            PublicationState::from($row['publication_state']),
            self::dateOrNull($row['publish_at']),
            self::dateOrNull($row['rsvp_closed_at']),
            self::dateOrNull($row['material_changed_at']),
            (int) $row['attendance_count'],
        );
    }

    /** @param array<string, mixed> $row */
    private static function adminEvent(array $row): AdminEvent
    {
        return new AdminEvent(
            (string) $row['id'],
            (string) $row['public_id'],
            (string) $row['round_id'],
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

    /** @return array<string, string|null> */
    private static function adminParameters(AdminEvent $event): array
    {
        return [
            'id' => $event->id,
            'public_id' => $event->publicId,
            'round_id' => $event->roundId,
            'title' => $event->details->title,
            'starts_at' => self::format($event->details->timing->startsAt),
            'ends_at' => $event->details->timing->endsAt === null ? null : self::format($event->details->timing->endsAt),
            'location' => $event->details->location,
            'note' => $event->details->note,
            'publication_state' => $event->publicationState->value,
            'publish_at' => $event->publishAt === null ? null : self::format($event->publishAt),
            'rsvp_closed_at' => $event->rsvpClosedAt === null ? null : self::format($event->rsvpClosedAt),
            'material_changed_at' => $event->materialChangedAt === null ? null : self::format($event->materialChangedAt),
            'created_at' => self::format($event->createdAt),
            'updated_at' => self::format($event->updatedAt),
            'delete_at' => self::format($event->deleteAt),
        ];
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(self::FORMAT, $value, new DateTimeZone('UTC'));
        if ($date === false) {
            throw new \UnexpectedValueException('Invalid fixture date.');
        }

        return $date;
    }

    private static function dateOrNull(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::date($value);
    }

    private static function format(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
