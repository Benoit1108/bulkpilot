<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Program\ProgramRepository;

final readonly class ActivateProgramHandler implements CommandHandler
{
    public function __construct(private ProgramRepository $programs)
    {
    }

    /**
     * Two activations racing: the unique index on the active program refuses the second one
     * (409, retry).
     */
    public function __invoke(ActivateProgram $command): void
    {
        // Loaded first, so that it is written first: the index must see it inactive before the
        // new one becomes active (see PersistenceTest).
        $active = $this->programs->activeOf($command->userId);
        $program = $this->programs->getVisibleTo($command->programId, $command->userId);
        if (null !== $active && !$active->id()->equals($program->id())) {
            $active->deactivate();
            $this->programs->save($active);
        }
        $program->activate();
        $this->programs->save($program);
    }
}
