<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Program\PlannedExerciseView;
use App\Training\Presentation\Api\Exercise\ExerciseChoices;

final readonly class ProgramExercise
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $exerciseId,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::MEASUREMENTS])]
        public string $measurement,
        /** False once the user deleted this exercise of theirs (it stays in the program). */
        #[ApiProperty(required: true)]
        public bool $available,
        #[ApiProperty(required: true)]
        public int $sets,
        #[ApiProperty(required: true)]
        public int $warmupSets,
        #[ApiProperty(required: true)]
        public int $repMin,
        #[ApiProperty(required: true)]
        public int $repMax,
        #[ApiProperty(required: true)]
        public ?int $targetRir,
        #[ApiProperty(required: true)]
        public int $restSeconds,
        #[ApiProperty(required: true)]
        public ?string $tempo,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::STRATEGIES])]
        public string $strategy,
        #[ApiProperty(required: true)]
        public float $incrementKg,
        /** A drop set after the last working set. */
        #[ApiProperty(required: true)]
        public bool $dropSet,
        #[ApiProperty(required: true)]
        public ?string $notes,
    ) {
    }

    public static function fromView(PlannedExerciseView $view): self
    {
        $planned = $view->planned;

        return new self(
            $planned->exerciseId->value,
            $view->name,
            $view->measurement->value,
            $view->available,
            $planned->sets,
            $planned->warmupSets,
            $planned->repRange->min,
            $planned->repRange->max,
            $planned->targetRir,
            $planned->restSeconds,
            $planned->tempo?->value,
            $planned->strategy->value,
            $planned->incrementKg,
            $planned->dropSet,
            $planned->notes,
        );
    }
}
