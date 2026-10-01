<?php

declare(strict_types=1);

namespace App\System\Presentation\Cli;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\Backups;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backup:create', description: 'Dumps the database, encrypts it and sends it to the backup bucket; deletes the backups past the retention.')]
final readonly class CreateBackupCommand
{
    public function __construct(private Backups $backups)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $created = $this->backups->create();
        } catch (BackupFailed $failure) {
            $io->error('Backup failed: '.$failure->getMessage());

            return Command::FAILURE;
        }
        $io->success(\sprintf('Backup %s sent; %d past the retention deleted.', $created->key, $created->deleted));

        return Command::SUCCESS;
    }
}
