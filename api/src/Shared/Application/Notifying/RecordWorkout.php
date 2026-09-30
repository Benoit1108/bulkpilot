<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

final readonly class RecordWorkout
{
    public function __construct(public string $workoutId, public int $records)
    {
    }
}
