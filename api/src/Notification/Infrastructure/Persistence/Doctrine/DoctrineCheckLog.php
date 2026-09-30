<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence\Doctrine;

use App\Notification\Domain\Check\CheckLog;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCheckLog implements CheckLog
{
    public function __construct(private Connection $connection)
    {
    }

    public function takeTurn(UserId $userId, \DateTimeImmutable $now): bool
    {
        // One statement: the row is created, or moved on only when old enough. A concurrent check
        // waits on the row lock until the first one's transaction ends, then sees it too recent.
        return 1 === $this->connection->executeStatement(
            'INSERT INTO notification_checks (owner_id, checked_at) VALUES (:owner, :now)
             ON CONFLICT (owner_id) DO UPDATE SET checked_at = EXCLUDED.checked_at
             WHERE notification_checks.checked_at <= :limit',
            ['owner' => $userId->value, 'now' => $now, 'limit' => $now->modify(\sprintf('-%d minutes', self::INTERVAL_MINUTES))],
            ['now' => 'datetimetz_immutable', 'limit' => 'datetimetz_immutable'],
        );
    }
}
