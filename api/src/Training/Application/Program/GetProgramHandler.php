<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;

final readonly class GetProgramHandler implements QueryHandler
{
    public function __construct(private ProgramRepository $programs, private ProgramVersionRepository $versions, private ProgramExercises $exercises)
    {
    }

    /**
     * @throws ProgramNotFound also for a version number it does not have
     */
    public function __invoke(GetProgram $query): ProgramView
    {
        $program = $this->programs->getVisibleTo($query->programId, $query->userId);
        $version = null === $query->versionNumber ? $this->versions->currentOf($program) : $this->versions->numbered($program, $query->versionNumber);

        return ProgramView::of($program, $version, $this->exercises->named($query->userId, $version->exerciseIds()), $this->versions->historyOf($program));
    }
}
