<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Workout\PreviousPerformance;

/**
 * The sets of this exercise the last time it was done.
 */
final readonly class PreviousOutput
{
    /**
     * @param list<WorkoutSetOutput> $sets
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $date,
        #[ApiProperty(required: true)]
        public array $sets,
    ) {
    }

    public static function of(?PreviousPerformance $previous): ?self
    {
        return null === $previous ? null : new self($previous->date, array_map(WorkoutSetOutput::of(...), $previous->sets));
    }
}
