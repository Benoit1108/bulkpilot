<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Exception\NoActiveProgram;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionRepository;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;

/**
 * The next day is the one after the day of the latest finished workout of the active program
 * ({@see ProgramVersion::dayAfter}). When it was last done counts the workouts of the current
 * version only: a revision may have put another day at the same place.
 */
final readonly class GetNextWorkoutHandler implements QueryHandler
{
    /** How far back the latest workout of the program is looked for: a few weeks of training. */
    public const int LOOK_BACK = 60;

    public function __construct(
        private ProgramRepository $programs,
        private ProgramVersionRepository $versions,
        private WorkoutRepository $workouts,
    ) {
    }

    public function __invoke(GetNextWorkout $query): NextWorkoutView
    {
        $program = $this->programs->activeOf($query->userId) ?? throw NoActiveProgram::create();
        $version = $this->versions->currentOf($program);
        $ofProgram = array_values(array_filter(
            $this->workouts->historyOf($query->userId, self::LOOK_BACK),
            static fn (Workout $workout): bool => $workout->programDay()?->programId->equals($program->id()) ?? false,
        ));
        $latest = ([] === $ofProgram ? null : $ofProgram[0])?->programDay();
        $index = $version->dayAfter($latest?->dayIndex);
        $day = $version->day($index);
        $lastDone = null;
        foreach ($ofProgram as $workout) {
            $ref = $workout->programDay();
            if (null !== $ref && $ref->dayIndex === $index && $ref->versionId->equals($version->id())) {
                $lastDone = $workout->startedAt();
                break;
            }
        }

        return new NextWorkoutView(
            $program->id()->value,
            $program->name(),
            $program->currentVersionNumber(),
            $index,
            $day->name,
            \count($day->exerciseIds()),
            $lastDone,
            null !== $this->workouts->inProgressOf($query->userId),
        );
    }
}
