<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Doctrine\DBAL\Connection;

final readonly class ProbeCommandHandler implements CommandHandler
{
    public function __construct(
        private Connection $connection,
        private EventBus $eventBus,
        private Journal $journal,
    ) {
    }

    public function __invoke(ProbeCommand $command): void
    {
        $this->connection->insert('bus_probe', ['id' => $command->id, 'source' => 'handler']);
        $this->eventBus->publish(new ProbeHappened($command));

        if ($command->failInHandler) {
            throw new ProbeFailure('handler failed');
        }

        $this->journal->write('handler returned');
    }
}
