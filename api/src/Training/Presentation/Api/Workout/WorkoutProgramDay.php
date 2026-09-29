<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Workout\WorkoutView;

/**
 * The program day a workout follows (null for a free workout).
 */
final readonly class WorkoutProgramDay
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $programId,
        /** As it was named when the workout started. */
        #[ApiProperty(required: true)]
        public string $programName,
        #[ApiProperty(required: true)]
        public int $versionNumber,
        #[ApiProperty(required: true)]
        public int $dayIndex,
        #[ApiProperty(required: true)]
        public string $dayName,
    ) {
    }

    public static function of(WorkoutView $view): ?self
    {
        $day = $view->programDay;

        return null === $day ? null : new self($day->programId->value, $day->programName, $day->versionNumber, $day->dayIndex, $day->dayName);
    }
}
