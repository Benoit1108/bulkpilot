<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Domain\Exception\ConcurrentUpdate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    use UnwrapsHandlerFailures;

    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->unwrap(fn () => $this->bus->dispatch($command));
        } catch (UniqueConstraintViolationException|OptimisticLockException) {
            // Two requests raced past the handler's checks (e.g. a double submit): the database
            // constraint or version column caught it, the transaction was rolled back.
            throw ConcurrentUpdate::create();
        } catch (DelayedMessageHandlingException $e) {
            // Domain events are delivered after the command's transaction committed: the write
            // succeeded, so reporting an error would make the client retry and apply it twice.
            // Reactions that must not be lost are routed to the async transport (retries + failed queue).
            $this->logger->error('A domain event subscriber failed after command {command} was committed.', [
                'command' => $command::class,
                'exception' => $e,
            ]);
        }
    }
}
