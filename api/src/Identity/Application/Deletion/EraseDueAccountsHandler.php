<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * One asynchronous erasure per account: a failing one is retried alone.
 */
final readonly class EraseDueAccountsHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private CommandBus $commandBus, private ClockInterface $clock)
    {
    }

    public function __invoke(EraseDueAccounts $command): void
    {
        foreach ($this->users->dueForErasure($this->clock->now()) as $userId) {
            $this->commandBus->dispatch(new EraseAccount($userId));
        }
    }
}
