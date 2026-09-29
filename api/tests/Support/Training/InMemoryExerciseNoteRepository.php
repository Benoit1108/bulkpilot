<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteRepository;

final class InMemoryExerciseNoteRepository implements ExerciseNoteRepository
{
    /** @var array<string, ExerciseNote> */
    public array $notes = [];

    public function save(ExerciseNote $note): void
    {
        $this->notes[$note->id()->value] = $note;
    }

    public function remove(ExerciseNote $note): void
    {
        unset($this->notes[$note->id()->value]);
    }

    public function of(UserId $userId, ExerciseId $exerciseId): ?ExerciseNote
    {
        return array_find($this->notes, static fn (ExerciseNote $note): bool => $note->ownerId()->equals($userId) && $note->exerciseId()->equals($exerciseId));
    }

    public function forExercises(UserId $userId, array $exerciseIds): array
    {
        $notes = [];
        foreach ($exerciseIds as $id) {
            $note = $this->of($userId, $id);
            if (null !== $note) {
                $notes[$id->value] = $note;
            }
        }

        return $notes;
    }
}
