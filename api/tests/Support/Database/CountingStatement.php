<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * @internal
 */
final class CountingStatement extends AbstractStatementMiddleware
{
    /** @var array<int|string, mixed> */
    private array $params = [];

    public function __construct(Statement $statement, private readonly QueryCounter $counter, private readonly string $sql)
    {
        parent::__construct($statement);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;
        parent::bindValue($param, $value, $type);
    }

    public function execute(): Result
    {
        $this->counter->record($this->sql, $this->params);

        return parent::execute();
    }
}
