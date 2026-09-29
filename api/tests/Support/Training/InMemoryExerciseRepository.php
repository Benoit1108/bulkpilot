<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Exercise\SearchKey;

final class InMemoryExerciseRepository implements ExerciseRepository
{
    /** @var array<string, Exercise> */
    public array $exercises = [];

    public function save(Exercise $exercise): void
    {
        $this->exercises[$exercise->id()->value] = $exercise;
    }

    public function getVisibleTo(ExerciseId $id, UserId $userId): Exercise
    {
        $exercise = $this->exercises[$id->value] ?? null;
        if (null === $exercise || !$exercise->isVisibleTo($userId)) {
            throw ExerciseNotFound::withId($id->value);
        }

        return $exercise;
    }

    public function search(UserId $userId, string $query, ?Muscle $muscle, int $limit): array
    {
        $found = array_values(array_filter($this->exercises, static function (Exercise $exercise) use ($userId, $query, $muscle): bool {
            $key = SearchKey::of($exercise->name());

            return $exercise->isVisibleTo($userId)
                && array_all(SearchKey::words($query), static fn (string $word): bool => str_contains($key, $word))
                && (null === $muscle || $exercise->primaryMuscle() === $muscle || \in_array($muscle, $exercise->secondaryMuscles(), true));
        }));
        usort($found, static fn (Exercise $a, Exercise $b): int => [null === $a->ownerId(), SearchKey::of($a->name())] <=> [null === $b->ownerId(), SearchKey::of($b->name())]);

        return \array_slice($found, 0, $limit);
    }

    public function usableAmong(UserId $userId, array $ids): array
    {
        $usable = [];
        foreach ($ids as $id) {
            $exercise = $this->exercises[$id->value] ?? null;
            if (null !== $exercise && $exercise->isUsableBy($userId)) {
                $usable[$id->value] = $exercise;
            }
        }

        return $usable;
    }

    public function countCustomOf(UserId $userId): int
    {
        return \count(array_filter($this->exercises, static fn (Exercise $exercise): bool => $exercise->isEditableBy($userId)));
    }

    public function library(): array
    {
        $library = [];
        foreach ($this->exercises as $exercise) {
            if (null !== $exercise->libraryKey()) {
                $library[$exercise->libraryKey()] = $exercise;
            }
        }

        return $library;
    }
}
