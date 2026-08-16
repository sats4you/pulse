<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Credentials;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class PdoRoundProvisioningStore implements RoundProvisioningStore
{
    public function __construct(private PDO $connection)
    {
    }

    public function createRound(
        string $roundId,
        string $publicSlug,
        string $name,
        string $timezone,
        string $participantDigest,
        string $administratorDigest,
        string $recoveryDigest,
        DateTimeImmutable $createdAt,
    ): void {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                <<<'SQL'
                    INSERT INTO coordination_rounds (
                        id,
                        public_slug,
                        name,
                        timezone,
                        participant_access_digest,
                        participant_access_version,
                        admin_access_digest,
                        admin_access_version,
                        admin_recovery_digest,
                        admin_recovery_version,
                        created_at,
                        updated_at
                    ) VALUES (
                        UNHEX(:round_id),
                        :slug,
                        :name,
                        :timezone,
                        :participant_digest,
                        1,
                        :administrator_digest,
                        1,
                        :recovery_digest,
                        1,
                        :created_at,
                        :updated_at
                    )
                    SQL,
            );
            $statement->bindValue('round_id', $roundId);
            $statement->bindValue('slug', $publicSlug);
            $statement->bindValue('name', $name);
            $statement->bindValue('timezone', $timezone);
            $statement->bindValue('participant_digest', $participantDigest, PDO::PARAM_LOB);
            $statement->bindValue('administrator_digest', $administratorDigest, PDO::PARAM_LOB);
            $statement->bindValue('recovery_digest', $recoveryDigest, PDO::PARAM_LOB);
            $timestamp = $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $statement->bindValue('created_at', $timestamp);
            $statement->bindValue('updated_at', $timestamp);
            $statement->execute();
            $this->connection->commit();
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
    }
}
