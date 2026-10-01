<?php

declare(strict_types=1);

namespace App\System\Presentation\Cli;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\Backups;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backup:verify', description: 'Restores the latest backup into a throwaway database and checks it; fails loudly otherwise.')]
final readonly class VerifyBackupCommand
{
    public function __construct(private Backups $backups)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Fail unless the restored database holds at least this many accounts (the CI knows it created some).')]
        int $minAccounts = 0,
    ): int {
        try {
            $verified = $this->backups->verify();
        } catch (BackupFailed $failure) {
            $io->error('Backup verification failed: '.$failure->getMessage());

            return Command::FAILURE;
        }
        $check = $verified->check;
        if ($check->accounts() < $minAccounts) {
            $io->error(\sprintf('Backup %s restored only %d accounts (at least %d expected).', $verified->key, $check->accounts(), $minAccounts));

            return Command::FAILURE;
        }
        $rows = implode(', ', array_map(static fn (string $table, int $count): string => \sprintf('%s %d', $table, $count), array_keys($check->rows), $check->rows));
        $io->success(\sprintf('Backup %s restored: %d tables, migration %s, rows: %s; %d erased accounts to erase again after a restore.', $verified->key, $check->tables, $check->migration, $rows, $check->erasedToReapply));

        return Command::SUCCESS;
    }
}
