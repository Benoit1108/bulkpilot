<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Health;

use App\System\Application\Health\HealthCheck;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private Connection $connection)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function isHealthy(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (DbalException) {
            return false;
        }
    }
}
