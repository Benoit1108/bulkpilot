<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Program\ProgramRepository;
use Psr\Clock\ClockInterface;

final readonly class DeleteProgramHandler implements CommandHandler
{
    public function __construct(private ProgramRepository $programs, private ClockInterface $clock)
    {
    }

    public function __invoke(DeleteProgram $command): void
    {
        $program = $this->programs->getVisibleTo($command->programId, $command->userId);
        $program->archive($this->clock->now());
        $this->programs->save($program);
    }
}
