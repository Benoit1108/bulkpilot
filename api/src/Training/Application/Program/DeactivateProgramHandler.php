<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Program\ProgramRepository;

final readonly class DeactivateProgramHandler implements CommandHandler
{
    public function __construct(private ProgramRepository $programs)
    {
    }

    public function __invoke(DeactivateProgram $command): void
    {
        $program = $this->programs->getVisibleTo($command->programId, $command->userId);
        $program->deactivate();
        $this->programs->save($program);
    }
}
