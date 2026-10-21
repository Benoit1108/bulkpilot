<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Workout\Workout;

/**
 * How many working sets each muscle got: a set counts once, for the main muscle of its exercise
 * (warm-ups aside). The most worked first, the body's order between equals.
 */
final class MuscleVolume
{
    /**
     * @param list<Workout> $workouts
     *
     * @return list<ExerciseId> the exercises of their working sets, once each
     */
    public static function exercisesOf(array $workouts): array
    {
        $ids = [];
        foreach ($workouts as $workout) {
            $exercises = $workout->exercises();
            foreach ($workout->sets() as $set) {
                if ($set->kind->isWorking() && isset($exercises[$set->exerciseIndex])) {
                    $id = $exercises[$set->exerciseIndex]->exerciseId;
                    $ids[$id->value] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param list<Workout>           $workouts
     * @param array<string, Exercise> $exercises by id; a set whose exercise is missing is not counted
     *
     * @return array<string, int> working sets by muscle
     */
    public static function workingSetsByMuscle(array $workouts, array $exercises): array
    {
        $sets = [];
        foreach ($workouts as $workout) {
            $inWorkout = $workout->exercises();
            foreach ($workout->sets() as $set) {
                $exercise = $exercises[($inWorkout[$set->exerciseIndex] ?? null)?->exerciseId->value ?? ''] ?? null;
                if ($set->kind->isWorking() && null !== $exercise) {
                    $muscle = $exercise->primaryMuscle()->value;
                    $sets[$muscle] = ($sets[$muscle] ?? 0) + 1;
                }
            }
        }
        // In the body's order, then the most worked first (the sort keeps equals in place).
        $ordered = [];
        foreach (Muscle::cases() as $muscle) {
            if (isset($sets[$muscle->value])) {
                $ordered[$muscle->value] = $sets[$muscle->value];
            }
        }
        arsort($ordered);

        return $ordered;
    }
}
