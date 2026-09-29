<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Training\Application\Progress\ExerciseProgressView;
use App\Training\Application\Progress\SessionPoint;
use App\Training\Presentation\Api\Workout\RecordOutput;

/**
 * Personal records of an exercise and its workouts over time.
 */
#[ApiResource(
    shortName: 'ExerciseProgress',
    operations: [
        new Get(uriTemplate: '/exercises/{id}/progress', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Records and one point per finished workout, oldest first.', provider: ExerciseProgressProvider::class),
    ],
)]
final readonly class ExerciseProgress
{
    /**
     * @param list<RecordOutput>  $records
     * @param list<SessionOutput> $sessions
     */
    public function __construct(
        /** The exercise. */
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public array $records,
        #[ApiProperty(required: true)]
        public array $sessions,
    ) {
    }

    public static function fromView(ExerciseProgressView $view): self
    {
        return new self(
            $view->exerciseId,
            array_map(RecordOutput::of(...), $view->records),
            array_map(static fn (SessionPoint $point): SessionOutput => new SessionOutput($point->workoutId, $point->date, $point->workingSets, $point->topLoadKg, $point->estimatedOneRepMax, $point->volumeKg, $point->bestReps, $point->bestSeconds), $view->sessions),
        );
    }
}
