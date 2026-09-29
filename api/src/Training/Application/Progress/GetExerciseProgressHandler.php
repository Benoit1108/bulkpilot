<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class GetExerciseProgressHandler implements QueryHandler
{
    public function __construct(private ExerciseRepository $exercises, private WorkoutRepository $workouts)
    {
    }

    public function __invoke(GetExerciseProgress $query): ExerciseProgressView
    {
        $exercise = $this->exercises->getVisibleTo($query->exerciseId, $query->userId);
        $history = array_values(array_filter(
            $this->workouts->historyWith($query->userId, [$exercise->id()]),
            static fn ($workout): bool => $workout->measurementOf($exercise->id()) === $exercise->measurement(),
        ));

        return new ExerciseProgressView(
            $exercise->id()->value,
            array_values(RecordBook::bests($history, $exercise->id())),
            array_values(array_filter(array_map(static fn ($workout): ?SessionPoint => SessionPoint::of($workout, $exercise->id()), $history))),
        );
    }
}
