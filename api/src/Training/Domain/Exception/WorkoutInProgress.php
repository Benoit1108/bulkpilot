<?php

declare(strict_types=1);

namespace App\Training\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

/**
 * One workout at a time: the one in progress is finished (or discarded) before another starts.
 */
final class WorkoutInProgress extends Conflict
{
    public static function already(string $id): self
    {
        return new self(\sprintf('Workout "%s" is still in progress: finish or discard it first.', $id));
    }

    public function errorCode(): string
    {
        return 'training.workout_in_progress';
    }
}
