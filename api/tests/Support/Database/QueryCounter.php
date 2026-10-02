<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Counts the SQL statements sent to the database (test environment only): the query budgets of
 * the API tests catch an N+1 as soon as it appears (docs/TESTING.md).
 */
final class QueryCounter implements Middleware
{
    /**
     * Process-wide: the test isolation keeps one database connection for the whole run, wrapped by
     * the counter of the first kernel that connected, whichever kernel serves the request now.
     *
     * @var list<array{sql: string, params: array<int|string, mixed>}>
     */
    private static array $statements = [];

    public function wrap(Driver $driver): Driver
    {
        $counter = $this;

        return new class($driver, $counter) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly QueryCounter $counter)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new CountingConnection(parent::connect($params), $this->counter);
            }
        };
    }

    /**
     * @param array<int|string, mixed> $params as bound, to explain the statement later
     */
    public function record(string $sql, array $params = []): void
    {
        self::$statements[] = ['sql' => $sql, 'params' => $params];
    }

    public function reset(): void
    {
        self::$statements = [];
    }

    /**
     * The statements since the last reset, transaction control left out (savepoints of the
     * test isolation, BEGIN/COMMIT of the command bus).
     *
     * @return list<array{sql: string, params: array<int|string, mixed>}>
     */
    public function executed(): array
    {
        return array_values(array_filter(self::$statements, static fn (array $statement): bool => 1 !== preg_match('/^\s*(SAVEPOINT|RELEASE|ROLLBACK|BEGIN|COMMIT|START TRANSACTION)\b/i', $statement['sql'])));
    }
}
