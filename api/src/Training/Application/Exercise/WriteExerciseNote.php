<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNoteId;

/**
 * Sets, rewrites or (blank) removes the user's cue for an exercise.
 */
final readonly class WriteExerciseNote implements Command
{
    public function __construct(public UserId $userId, public ExerciseId $exerciseId, public ?string $text, public ExerciseNoteId $noteId)
    {
    }
}
