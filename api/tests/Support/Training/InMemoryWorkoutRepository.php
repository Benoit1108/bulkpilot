<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutRepository;

final class InMemoryWorkoutRepository implements WorkoutRepository
{
    /** @var array<string, Workout> */
    public array $workouts = [];

    public function save(Workout $workout): void
    {
        $this->workouts[$workout->id()->value] = $workout;
    }

    public function find(WorkoutId $id): ?Workout
    {
        return $this->workouts[$id->value] ?? null;
    }

    public function getVisibleTo(WorkoutId $id, UserId $userId): Workout
    {
        $workout = $this->find($id);
        if (null === $workout || !$workout->isVisibleTo($userId)) {
            throw WorkoutNotFound::withId($id->value);
        }

        return $workout;
    }

    public function inProgressOf(UserId $userId): ?Workout
    {
        return array_find($this->workouts, static fn (Workout $workout): bool => $workout->belongsTo($userId) && $workout->isInProgress());
    }

    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $finished = array_values(array_filter($this->finished($userId), static fn (Workout $workout): bool => null === $before || $workout->startedAt() < $before));
        usort($finished, static fn (Workout $a, Workout $b): int => [$b->startedAt(), $b->id()->value] <=> [$a->startedAt(), $a->id()->value]);

        return \array_slice($finished, 0, $limit);
    }

    public function historyWith(UserId $userId, array $exerciseIds, ?\DateTimeImmutable $before = null): array
    {
        $with = array_values(array_filter($this->finished($userId), static fn (Workout $workout): bool => (null === $before || $workout->startedAt() < $before)
            && array_any($workout->exercises(), static fn (WorkoutExercise $exercise): bool => array_any($exerciseIds, static fn (ExerciseId $id): bool => $exercise->exerciseId->equals($id)))));
        usort($with, static fn (Workout $a, Workout $b): int => [$b->startedAt(), $b->id()->value] <=> [$a->startedAt(), $a->id()->value]);

        return array_reverse(\array_slice($with, 0, self::HISTORY_LIMIT));
    }

    /**
     * @return list<Workout>
     */
    private function finished(UserId $userId): array
    {
        return array_values(array_filter($this->workouts, static fn (Workout $workout): bool => $workout->isVisibleTo($userId) && null !== $workout->finishedAt()));
    }
}
