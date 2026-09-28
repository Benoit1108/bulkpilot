<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use Doctrine\DBAL\Connection;

final readonly class ProbeSubscriber implements DomainEventSubscriber
{
    public function __construct(private Connection $connection, private Journal $journal)
    {
    }

    public function __invoke(ProbeHappened $event): void
    {
        $this->journal->write('subscriber ran, in transaction: '.($this->connection->isTransactionActive() ? 'yes' : 'no'));

        if ($event->command->failInSubscriber) {
            throw new ProbeFailure('subscriber failed');
        }

        $this->connection->insert('bus_probe', ['id' => $event->command->id, 'source' => 'subscriber']);
    }
}
