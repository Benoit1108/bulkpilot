<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Cli;

use App\Nutrition\Application\Catalogue\ImportCatalogue;
use App\Shared\Application\Bus\Command\CommandBus;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'nutrition:catalogue:import', description: 'Imports or refreshes the CIQUAL food table (idempotent).')]
final readonly class ImportCatalogueCommand
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Directory of the extracted CIQUAL XML files, or URL of the archive (default: the official ANSES download).')]
        ?string $location = null,
    ): int {
        $command = new ImportCatalogue($location);
        $this->commandBus->dispatch($command);
        $report = $command->report;

        $io->success(\sprintf('%d foods created, %d refreshed, %d skipped.', $report->created, $report->updated, \count($report->skipped)));
        if ($io->isVerbose()) {
            foreach ($report->skipped as $reference => $reason) {
                $io->writeln(\sprintf('  skipped %s: %s', $reference, $reason));
            }
        }

        return Command::SUCCESS;
    }
}
