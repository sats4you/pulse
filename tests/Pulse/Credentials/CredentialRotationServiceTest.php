<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Credentials;

use DateTimeImmutable;
use DomainException;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Credentials\CredentialRotationService;
use Sats4you\Pulse\Pulse\PublicAccess\AccessGrant;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Sats4you\Pulse\Tests\Support\SqlitePulseStore;

final class CredentialRotationServiceTest extends TestCase
{
    private const ID = '0123456789abcdef0123456789abcdef';
    private const SLUG = 'bern-bitcoin';

    public function testRecoveryAtomicallyReplacesAdministratorAndRecoverySecrets(): void
    {
        [$connection, $service, $digester] = $this->fixture();
        $grant = new AccessGrant(self::ID, AccessRole::Recovery, 1);

        $result = $service->recoverAdministrator($grant, self::SLUG, new DateTimeImmutable('2026-08-16T12:00:00Z'));

        $row = $connection->query('SELECT * FROM rounds')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(2, (int) $row['admin_version']);
        self::assertSame(2, (int) $row['recovery_version']);
        self::assertTrue($digester->matches($result->administratorSecret, $row['admin_digest']));
        self::assertTrue($digester->matches($result->recoverySecret, $row['recovery_digest']));
        self::assertNotSame($result->administratorSecret, $row['admin_digest']);
        self::assertNotSame($result->recoverySecret, $row['recovery_digest']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('credential_grant_stale');
        $service->recoverAdministrator($grant, self::SLUG, new DateTimeImmutable('2026-08-16T12:01:00Z'));
    }

    public function testParticipantRotationInvalidatesOnlyParticipantAccess(): void
    {
        [$connection, $service, $digester] = $this->fixture();
        $grant = new AccessGrant(self::ID, AccessRole::Administrator, 1);

        $result = $service->rotateParticipant($grant, self::SLUG, new DateTimeImmutable('2026-08-16T12:00:00Z'));

        $row = $connection->query('SELECT * FROM rounds')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(2, (int) $row['access_version']);
        self::assertSame(1, (int) $row['admin_version']);
        self::assertSame(1, (int) $row['recovery_version']);
        self::assertTrue($digester->matches($result->participantSecret, $row['participant_digest']));
    }

    public function testWrongRoleAndWrongRoundAreRejectedWithoutWriting(): void
    {
        [$connection, $service] = $this->fixture();

        try {
            $service->recoverAdministrator(
                new AccessGrant(self::ID, AccessRole::Administrator, 1),
                self::SLUG,
                new DateTimeImmutable(),
            );
            self::fail('Wrong role was accepted.');
        } catch (DomainException $error) {
            self::assertSame('recovery_grant_required', $error->getMessage());
        }

        try {
            $service->rotateParticipant(
                new AccessGrant(str_repeat('f', 32), AccessRole::Administrator, 1),
                self::SLUG,
                new DateTimeImmutable(),
            );
            self::fail('Wrong round was accepted.');
        } catch (DomainException $error) {
            self::assertSame('credential_grant_stale', $error->getMessage());
        }

        $row = $connection->query('SELECT * FROM rounds')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $row['access_version']);
        self::assertSame(1, (int) $row['admin_version']);
        self::assertSame(1, (int) $row['recovery_version']);
    }

    /** @return array{PDO, CredentialRotationService, SecretDigester} */
    private function fixture(): array
    {
        $connection = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $connection->exec('CREATE TABLE rounds (id TEXT PRIMARY KEY, slug TEXT UNIQUE, participant_digest BLOB, access_version INTEGER, admin_digest BLOB, admin_version INTEGER, recovery_digest BLOB, recovery_version INTEGER)');
        $digester = new SecretDigester(str_repeat('h', 32));
        $statement = $connection->prepare('INSERT INTO rounds VALUES (:id, :slug, :participant, 1, :admin, 1, :recovery, 1)');
        $statement->bindValue('id', self::ID);
        $statement->bindValue('slug', self::SLUG);
        $statement->bindValue('participant', $digester->digest('participant-old'), PDO::PARAM_LOB);
        $statement->bindValue('admin', $digester->digest('admin-old'), PDO::PARAM_LOB);
        $statement->bindValue('recovery', $digester->digest('recovery-old'), PDO::PARAM_LOB);
        $statement->execute();
        $service = new CredentialRotationService(
            new SqlitePulseStore($connection),
            new SecretGenerator(),
            $digester,
        );

        return [$connection, $service, $digester];
    }
}
