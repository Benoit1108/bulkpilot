<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use App\Training\Domain\Progression\ProgressionAdvisor;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutRepository;

/**
 * Builds workout views: each exercise with the user's cue, what was done last time, what to aim
 * for today and the records (those before the workout, and those it beat).
 */
final readonly class WorkoutViews
{
    public function __construct(private WorkoutRepository $workouts, private ExerciseNoteRepository $notes)
    {
    }

    public function of(Workout $workout, UserId $userId): WorkoutView
    {
        return new WorkoutView(
            $workout->id()->value,
            $workout->programDay(),
            $workout->startedAt(),
            $workout->finishedAt(),
            $this->exercises($userId, $workout->exercises(), $workout),
            $workout->sets(),
        );
    }

    public function ofPlan(WorkoutPlan $plan, UserId $userId): WorkoutView
    {
        return new WorkoutView(null, $plan->programDay, null, null, $this->exercises($userId, $plan->exercises, null), []);
    }

    /**
     * One query for the history of every exercise, from which come the last performance, the
     * suggestion and the records of each.
     *
     * @param list<WorkoutExercise> $exercises
     *
     * @return list<ExerciseInWorkoutView>
     */
    private function exercises(UserId $userId, array $exercises, ?Workout $workout): array
    {
        $ids = [];
        foreach ($exercises as $exercise) {
            $ids[$exercise->exerciseId->value] = $exercise->exerciseId;
        }
        $notes = $this->notes->forExercises($userId, array_values($ids));
        $history = array_values(array_filter(
            $this->workouts->historyWith($userId, array_values($ids), $workout?->startedAt()),
            static fn (Workout $past): bool => null === $workout || !$past->id()->equals($workout->id()),
        ));

        $views = [];
        foreach ($exercises as $index => $exercise) {
            $done = self::doneLike($history, $exercise);
            $last = [] === $done ? null : $done[array_key_last($done)];
            $previous = null === $last ? null : new PreviousPerformance($last->startedAt(), $last->setsOfExercise($exercise->exerciseId));
            $views[] = new ExerciseInWorkoutView(
                $index,
                $exercise,
                ($notes[$exercise->exerciseId->value] ?? null)?->text(),
                $previous,
                null === $previous ? null : ProgressionAdvisor::suggest($exercise->planned, $exercise->measurement, $previous->sets),
                RecordBook::bests($done, $exercise->exerciseId),
                null === $workout ? [] : RecordBook::beatenBy($workout, $done, $exercise->exerciseId),
            );
        }

        return $views;
    }

    /**
     * The workouts where this exercise was actually done (planned but skipped does not count),
     * measured the same way: a custom exercise changed from load to duration starts afresh.
     *
     * @param list<Workout> $history
     *
     * @return list<Workout>
     */
    private static function doneLike(array $history, WorkoutExercise $exercise): array
    {
        return array_values(array_filter(
            $history,
            static fn (Workout $past): bool => $past->measurementOf($exercise->exerciseId) === $exercise->measurement && [] !== $past->setsOfExercise($exercise->exerciseId),
        ));
    }
}
