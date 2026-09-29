<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;

final readonly class ListProgramsHandler implements QueryHandler
{
    public function __construct(private ProgramRepository $programs, private ProgramVersionRepository $versions, private ProgramExercises $exercises)
    {
    }

    /**
     * @return list<ProgramView> the current version of each (no history: see GetProgram)
     */
    public function __invoke(ListPrograms $query): array
    {
        $programs = $this->programs->programsOf($query->userId);
        $versions = $this->versions->currentOfEach($programs);
        $ids = [];
        foreach ($versions as $version) {
            foreach ($version->exerciseIds() as $id) {
                $ids[$id->value] = $id;
            }
        }
        $exercises = $this->exercises->named($query->userId, array_values($ids));

        return array_map(
            static fn (Program $program): ProgramView => ProgramView::of($program, $versions[$program->id()->value], $exercises, []),
            $programs,
        );
    }
}
