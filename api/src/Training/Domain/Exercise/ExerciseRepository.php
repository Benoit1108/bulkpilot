<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ExerciseNotFound;

interface ExerciseRepository
{
    public function save(Exercise $exercise): void;

    /**
     * @throws ExerciseNotFound when it does not exist, is deleted or is someone else's
     */
    public function getVisibleTo(ExerciseId $id, UserId $userId): Exercise;

    /**
     * Library and own exercises matching every word of the query and the muscle (primary or
     * secondary), the user's own first, then by name.
     *
     * @return list<Exercise>
     */
    public function search(UserId $userId, string $query, ?Muscle $muscle, int $limit): array;

    /**
     * @param list<ExerciseId> $ids
     *
     * @return array<string, Exercise> by id: those the user can see, and their own deleted ones
     *                                 (programs and history keep referring to them)
     */
    public function usableAmong(UserId $userId, array $ids): array;

    /**
     * The user's own exercises, deleted ones aside.
     */
    public function countCustomOf(UserId $userId): int;

    /**
     * @return array<string, Exercise> the library, by key
     */
    public function library(): array;
}
