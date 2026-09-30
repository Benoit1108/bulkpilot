<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

final readonly class RecordSession
{
    public function __construct(public string $workoutId, public int $records)
    {
    }
}
