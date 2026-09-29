<?php

declare(strict_types=1);

namespace App\Training\Presentation\Cli;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Training\Application\Library\ImportExerciseLibrary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'training:library:import', description: 'Imports or refreshes the exercise library shipped with the application (idempotent).')]
final readonly class ImportExerciseLibraryCommand
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $command = new ImportExerciseLibrary();
        $this->commandBus->dispatch($command);

        $io->success(\sprintf('%d exercises created, %d refreshed.', $command->report->created, $command->report->updated));

        return Command::SUCCESS;
    }
}
