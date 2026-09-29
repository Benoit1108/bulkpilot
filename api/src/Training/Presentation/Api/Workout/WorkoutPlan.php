<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Training\Application\Workout\WorkoutView;

/**
 * What starting a program day would plan, with each exercise's cue and last performance: the
 * device keeps it, to start the workout even offline.
 */
#[ApiResource(
    shortName: 'WorkoutPlan',
    operations: [
        new Get(
            uriTemplate: '/workouts/preview',
            description: 'The plan of a day of a program (its current version).',
            provider: WorkoutProvider::class,
            parameters: [
                'programId' => new QueryParameter(required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                'day' => new QueryParameter(description: 'Index of the day in the program, from 0.', required: true, schema: ['type' => 'integer', 'minimum' => 0, 'maximum' => 6]),
            ],
        ),
    ],
)]
final readonly class WorkoutPlan
{
    /**
     * @param list<WorkoutExerciseOutput> $exercises
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $programId,
        #[ApiProperty(required: true)]
        public WorkoutProgramDay $programDay,
        #[ApiProperty(required: true)]
        public array $exercises,
    ) {
    }

    public static function fromView(WorkoutView $view): self
    {
        $day = WorkoutProgramDay::of($view);
        \assert(null !== $day);

        return new self($day->programId, $day, array_map(WorkoutExerciseOutput::of(...), $view->exercises));
    }
}
