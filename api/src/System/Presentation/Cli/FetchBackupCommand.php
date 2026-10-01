<?php

declare(strict_types=1);

namespace App\System\Presentation\Cli;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\Backups;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backup:fetch', description: 'Downloads a backup (the latest by default) and decrypts it into a dump pg_restore reads.')]
final readonly class FetchBackupCommand
{
    public function __construct(private Backups $backups)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Where to write the decrypted dump.')]
        string $output,
        #[Option('The backup to fetch (db/YYYY/MM/DD/HHMMSS.dump.enc); the latest by default.')]
        ?string $key = null,
    ): int {
        try {
            $key = $this->backups->fetch($output, $key);
        } catch (BackupFailed $failure) {
            $io->error('Fetch failed: '.$failure->getMessage());

            return Command::FAILURE;
        }
        $io->success(\sprintf('Backup %s decrypted into %s.', $key, $output));

        return Command::SUCCESS;
    }
}
