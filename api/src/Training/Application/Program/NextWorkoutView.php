<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

final readonly class NextWorkoutView
{
    public function __construct(
        public string $programId,
        public string $programName,
        public int $versionNumber,
        public int $dayIndex,
        public string $dayName,
        public int $exerciseCount,
        public ?\DateTimeImmutable $lastDoneAt,
        public bool $inProgress,
    ) {
    }
}
