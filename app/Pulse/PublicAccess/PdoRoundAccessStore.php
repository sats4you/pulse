<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\PublicAccess;

use PDO;

final readonly class PdoRoundAccessStore implements RoundAccessStore
{
    public function __construct(private PDO $connection)
    {
    }

    public function findParticipantGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        return $this->findGrant($publicSlug, $presentedDigest, AccessRole::Participant);
    }

    public function findAdministratorGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        return $this->findGrant($publicSlug, $presentedDigest, AccessRole::Administrator);
    }

    public function findRecoveryGrant(string $publicSlug, string $presentedDigest): ?AccessGrant
    {
        return $this->findGrant($publicSlug, $presentedDigest, AccessRole::Recovery);
    }

    private function findGrant(string $publicSlug, string $presentedDigest, AccessRole $role): ?AccessGrant
    {
        [$digestColumn, $versionColumn] = match ($role) {
            AccessRole::Participant => ['participant_access_digest', 'participant_access_version'],
            AccessRole::Administrator => ['admin_access_digest', 'admin_access_version'],
            AccessRole::Recovery => ['admin_recovery_digest', 'admin_recovery_version'],
        };
        $statement = $this->connection->prepare(
            <<<SQL
                SELECT HEX(id) AS round_id, {$digestColumn} AS access_digest, {$versionColumn} AS access_version
                FROM coordination_rounds
                WHERE public_slug = :slug AND delete_at IS NULL
                SQL,
        );
        $statement->execute(['slug' => $publicSlug]);
        $row = $statement->fetch();

        if ($row === false || !hash_equals($row['access_digest'], $presentedDigest)) {
            return null;
        }

        return new AccessGrant(
            strtolower($row['round_id']),
            $role,
            (int) $row['access_version'],
        );
    }

    public function isCurrent(AccessGrant $grant, string $publicSlug): bool
    {
        $versionColumn = match ($grant->role) {
            AccessRole::Participant => 'participant_access_version',
            AccessRole::Administrator => 'admin_access_version',
            AccessRole::Recovery => 'admin_recovery_version',
        };

        $statement = $this->connection->prepare(
            <<<SQL
                SELECT 1
                FROM coordination_rounds
                WHERE id = UNHEX(:round_id)
                  AND public_slug = :slug
                  AND {$versionColumn} = :version
                  AND delete_at IS NULL
                SQL,
        );
        $statement->execute([
            'round_id' => str_replace('-', '', $grant->roundId),
            'slug' => $publicSlug,
            'version' => $grant->accessVersion,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
