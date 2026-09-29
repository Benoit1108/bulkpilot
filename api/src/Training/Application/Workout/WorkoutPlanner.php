<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;
use App\Training\Domain\Workout\ProgramDayRef;
use App\Training\Domain\Workout\WorkoutExercise;

/**
 * What a workout plans: the exercises of a program day (as that version has them), or an
 * exercise added on the go with its usual values.
 */
final readonly class WorkoutPlanner
{
    public function __construct(private ProgramRepository $programs, private ProgramVersionRepository $versions, private ExerciseRepository $exercises)
    {
    }

    /**
     * @param int|null $versionNumber the version the device saw (the current one when null)
     *
     * @throws ProgramNotFound
     * @throws InvalidProgram  for a day the version does not have
     */
    public function day(UserId $userId, ProgramId $programId, ?int $versionNumber, int $dayIndex): WorkoutPlan
    {
        $program = $this->programs->getVisibleTo($programId, $userId);
        $version = null === $versionNumber ? $this->versions->currentOf($program) : $this->versions->numbered($program, $versionNumber);
        $day = $version->day($dayIndex);
        $usable = $this->exercises->usableAmong($userId, $day->exerciseIds());

        $exercises = [];
        foreach ($day->blocks as $block => $exerciseBlock) {
            foreach ($exerciseBlock->exercises as $planned) {
                $exercise = $usable[$planned->exerciseId->value] ?? throw ExerciseNotFound::withId($planned->exerciseId->value);
                $exercises[] = new WorkoutExercise($planned->exerciseId, $exercise->name(), $exercise->measurement(), $block, $planned);
            }
        }

        return new WorkoutPlan(new ProgramDayRef($program->id(), $program->name(), $version->id(), $version->number(), $dayIndex, $day->name), $exercises);
    }

    /**
     * An exercise done outside the plan, with its usual values.
     *
     * @throws ExerciseNotFound
     */
    public function usual(UserId $userId, ExerciseId $exerciseId): WorkoutExercise
    {
        $exercise = $this->exercises->getVisibleTo($exerciseId, $userId);

        return new WorkoutExercise($exercise->id(), $exercise->name(), $exercise->measurement(), 0, PlannedExercise::usual($exercise->id(), $exercise->defaults()), true);
    }
}
