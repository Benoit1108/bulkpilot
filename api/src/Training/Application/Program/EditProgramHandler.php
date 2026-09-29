<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;
use Psr\Clock\ClockInterface;

final readonly class EditProgramHandler implements CommandHandler
{
    public function __construct(
        private ProgramRepository $programs,
        private ProgramVersionRepository $versions,
        private ProgramExercises $exercises,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EditProgram $command): void
    {
        $program = $this->programs->getVisibleTo($command->programId, $command->userId);
        $current = $this->versions->currentOf($program);
        $days = $command->details->days();
        $this->exercises->assertUsable($command->userId, $days, $current);

        $program->rename($command->details->name);
        $next = $program->revise($current, $command->nextVersionId, $days, $this->clock->now());
        if (null !== $next) {
            $this->versions->save($next);
        }
        $this->programs->save($program);
    }
}
