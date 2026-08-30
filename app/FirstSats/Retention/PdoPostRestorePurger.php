<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Retention;

use PDO;
use Throwable;

final readonly class PdoPostRestorePurger
{
    public function __construct(private PDO $connection)
    {
    }

    public function purgeInvitationLinkedData(): int
    {
        $this->connection->beginTransaction();
        try {
            $count = (int) $this->connection->query('SELECT COUNT(*) FROM first_sats_invitations')->fetchColumn();
            $this->connection->exec('DELETE FROM first_sats_invitations');
            $this->connection->commit();

            return $count;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
    }
}
