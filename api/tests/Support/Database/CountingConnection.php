<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * @internal
 */
final class CountingConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly QueryCounter $counter)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new CountingStatement(parent::prepare($sql), $this->counter, $sql);
    }

    public function query(string $sql): Result
    {
        $this->counter->record($sql);

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->counter->record($sql);

        return parent::exec($sql);
    }
}
