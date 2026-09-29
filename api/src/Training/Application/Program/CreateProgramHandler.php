<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionRepository;
use Psr\Clock\ClockInterface;

final readonly class CreateProgramHandler implements CommandHandler
{
    public function __construct(
        private ProgramRepository $programs,
        private ProgramVersionRepository $versions,
        private ProgramExercises $exercises,
        private ClockInterface $clock,
    ) {
    }

    /** Far more than anyone needs; keeps a runaway client from filling the table. */
    public const int MAX_PER_USER = 50;

    public function __invoke(CreateProgram $command): void
    {
        if ($this->programs->countOf($command->userId) >= self::MAX_PER_USER) {
            throw InvalidProgram::because(\sprintf('at most %d programs: archive one first', self::MAX_PER_USER));
        }
        $days = $command->details->days();
        $this->exercises->assertUsable($command->userId, $days);
        $now = $this->clock->now();

        $first = ProgramVersion::first($command->versionId, $command->programId, $days, $now);
        $program = Program::create($command->programId, $command->userId, $command->details->name, $first, $now);
        if (null === $this->programs->activeOf($command->userId)) {
            $program->activate();
        }
        $this->versions->save($first);
        $this->programs->save($program);
    }
}
