<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Tests\Integration\Shared\Infrastructure\Bus\Fixture\Journal;
use App\Tests\Integration\Shared\Infrastructure\Bus\Fixture\ProbeCommand;
use App\Tests\Integration\Shared\Infrastructure\Bus\Fixture\ProbeFailure;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises the real command/event bus configuration (middleware order, transactions, deferral).
 */
final class BusWiringTest extends KernelTestCase
{
    private CommandBus $commandBus;
    private Connection $connection;
    private Journal $journal;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->commandBus = $container->get(CommandBus::class);
        $this->connection = $container->get(Connection::class);
        $this->journal = $container->get(Journal::class);

        // Rolled back with the rest of the test transaction (DAMA).
        $this->connection->executeStatement('CREATE TABLE bus_probe (id TEXT NOT NULL, source TEXT NOT NULL)');
    }

    public function testEventsPublishedByAHandlerAreDeliveredAfterItReturnsInTheirOwnTransaction(): void
    {
        $this->commandBus->dispatch(new ProbeCommand('ok'));

        self::assertSame(['handler returned', 'subscriber ran, in transaction: yes'], $this->journal->entries);
        self::assertSame([['id' => 'ok', 'source' => 'handler'], ['id' => 'ok', 'source' => 'subscriber']], $this->rows());
    }

    public function testAFailingHandlerRollsBackAndItsEventsAreNeverDelivered(): void
    {
        try {
            $this->commandBus->dispatch(new ProbeCommand('ko', failInHandler: true));
            self::fail('The handler exception must reach the caller.');
        } catch (ProbeFailure $e) {
            self::assertSame('handler failed', $e->getMessage());
        }

        self::assertSame([], $this->journal->entries);
        self::assertSame([], $this->rows());
    }

    public function testAFailingSubscriberDoesNotTurnACommittedCommandIntoAnError(): void
    {
        $this->commandBus->dispatch(new ProbeCommand('late', failInSubscriber: true));

        self::assertSame(['handler returned', 'subscriber ran, in transaction: yes'], $this->journal->entries);
        self::assertSame([['id' => 'late', 'source' => 'handler']], $this->rows());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT id, source FROM bus_probe ORDER BY source');
    }
}
