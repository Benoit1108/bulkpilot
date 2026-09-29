<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Training\Domain\Progression\PersonalRecord;

final readonly class ExerciseProgressView
{
    /**
     * @param list<PersonalRecord> $records
     * @param list<SessionPoint>   $sessions oldest first
     */
    public function __construct(public string $exerciseId, public array $records, public array $sessions)
    {
    }
}
