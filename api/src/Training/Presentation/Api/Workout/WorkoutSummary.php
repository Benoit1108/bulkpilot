<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Workout\WorkoutSummaryView;

final readonly class WorkoutSummary
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public ?string $dayName,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $startedAt,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $finishedAt,
        /** Exercises with at least one set. */
        #[ApiProperty(required: true)]
        public int $exercises,
        #[ApiProperty(required: true)]
        public int $workingSets,
        #[ApiProperty(required: true)]
        public float $volumeKg,
    ) {
    }

    public static function of(WorkoutSummaryView $view): self
    {
        return new self($view->id, $view->dayName, $view->startedAt, $view->finishedAt, $view->exercises, $view->workingSets, $view->volumeKg);
    }
}
