<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;

/**
 * The program day a workout followed, named as it was then: its version and its names stay the
 * ones it had (spec §49), whatever happens to the program since.
 */
final readonly class ProgramDayRef
{
    public function __construct(
        public ProgramId $programId,
        public string $programName,
        public ProgramVersionId $versionId,
        public int $versionNumber,
        public int $dayIndex,
        public string $dayName,
    ) {
    }
}
