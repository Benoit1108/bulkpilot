<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * Gone from search and new programs; programs and history that use it keep showing it.
 */
final readonly class DeleteExercise implements Command
{
    public function __construct(public ExerciseId $exerciseId, public UserId $userId)
    {
    }
}
