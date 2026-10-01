<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Cli;

use App\Identity\Application\Deletion\EraseAccount;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\PersonalData\ErasedAccounts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * After a restore (docs/DEPLOYMENT.md): the accounts erased since the backup was taken come back
 * with it; this erases them again, before the application is opened.
 */
#[AsCommand(name: 'app:backup:reapply-erasures', description: 'Erases again every account erased since the backups were taken (run after restoring one).')]
final readonly class ReapplyErasuresCommand
{
    public function __construct(private ErasedAccounts $erased, private CommandBus $commandBus)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $accounts = $this->erased->all();
        foreach ($accounts as $userId) {
            $this->commandBus->dispatch(new EraseAccount($userId, restored: true));
        }
        $io->success(\sprintf('%d erased accounts checked (those present again are erased).', \count($accounts)));

        return Command::SUCCESS;
    }
}
