<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\CommandHandler;
use Doctrine\DBAL\Connection;

final readonly class DuplicateProbeHandler implements CommandHandler
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(DuplicateProbe $command): void
    {
        $this->connection->insert('bus_probe_unique', ['id' => 'same']);
        $this->connection->insert('bus_probe_unique', ['id' => 'same']);
    }
}
