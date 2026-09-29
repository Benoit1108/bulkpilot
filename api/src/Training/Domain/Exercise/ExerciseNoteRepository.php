<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Shared\Domain\Identifier\UserId;

interface ExerciseNoteRepository
{
    public function save(ExerciseNote $note): void;

    public function remove(ExerciseNote $note): void;

    public function of(UserId $userId, ExerciseId $exerciseId): ?ExerciseNote;

    /**
     * @param list<ExerciseId> $exerciseIds
     *
     * @return array<string, ExerciseNote> the user's notes on these exercises, by exercise id
     */
    public function forExercises(UserId $userId, array $exerciseIds): array;
}
