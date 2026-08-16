<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class PdoCredentialStore implements CredentialStore
{
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

    public function getRoundForUpdate(string $publicSlug): ?RoundCredentialSnapshot
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
                SELECT
                    HEX(id) AS round_id,
                    public_slug,
                    participant_access_version,
                    admin_access_version,
                    admin_recovery_version
                FROM coordination_rounds
                WHERE public_slug = :slug
                  AND delete_at IS NULL
                FOR UPDATE
                SQL,
        );
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return new RoundCredentialSnapshot(
            strtolower((string) $row['round_id']),
            (string) $row['public_slug'],
            (int) $row['participant_access_version'],
            (int) $row['admin_access_version'],
            (int) $row['admin_recovery_version'],
        );
    }

    public function replaceAdministratorCredentials(
        RoundCredentialSnapshot $expected,
        string $administratorDigest,
        string $recoveryDigest,
        DateTimeImmutable $rotatedAt,
    ): bool {
        $statement = $this->connection->prepare(
            <<<'SQL'
                UPDATE coordination_rounds
                SET admin_access_digest = :admin_digest,
                    admin_access_version = admin_access_version + 1,
                    admin_recovery_digest = :recovery_digest,
                    admin_recovery_version = admin_recovery_version + 1,
                    admin_credentials_rotated_at = :credentials_rotated_at,
                    updated_at = :updated_at
                WHERE id = UNHEX(:round_id)
                  AND public_slug = :slug
                  AND admin_access_version = :admin_version
                  AND admin_recovery_version = :recovery_version
                  AND delete_at IS NULL
                SQL,
        );
        $statement->bindValue('admin_digest', $administratorDigest, PDO::PARAM_LOB);
        $statement->bindValue('recovery_digest', $recoveryDigest, PDO::PARAM_LOB);
        $timestamp = self::format($rotatedAt);
        $statement->bindValue('credentials_rotated_at', $timestamp);
        $statement->bindValue('updated_at', $timestamp);
        $statement->bindValue('round_id', self::normaliseId($expected->roundId));
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
            <<<'SQL'
                UPDATE coordination_rounds
                SET participant_access_digest = :participant_digest,
                    participant_access_version = participant_access_version + 1,
                    updated_at = :rotated_at
                WHERE id = UNHEX(:round_id)
                  AND public_slug = :slug
                  AND participant_access_version = :participant_version
                  AND admin_access_version = :admin_version
                  AND delete_at IS NULL
                SQL,
        );
        $statement->bindValue('participant_digest', $participantDigest, PDO::PARAM_LOB);
        $statement->bindValue('rotated_at', self::format($rotatedAt));
        $statement->bindValue('round_id', self::normaliseId($expected->roundId));
        $statement->bindValue('slug', $expected->publicSlug);
        $statement->bindValue('participant_version', $expected->participantVersion, PDO::PARAM_INT);
        $statement->bindValue('admin_version', $expected->administratorVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    private static function normaliseId(string $id): string
    {
        $normalised = strtolower(str_replace('-', '', $id));
        if (preg_match('/^[a-f0-9]{32}$/', $normalised) !== 1) {
            throw new \InvalidArgumentException('Identifier must contain 32 hexadecimal characters.');
        }

        return $normalised;
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
