<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * An exercise added to a workout, at the next position (sending it again changes nothing).
 */
#[ApiResource(
    shortName: 'WorkoutExercise',
    operations: [
        new Put(
            uriTemplate: '/workouts/{workoutId}/exercises/{index}',
            uriVariables: [
                'workoutId' => new Link(fromClass: self::class, identifiers: ['workoutId']),
                'index' => new Link(fromClass: self::class, identifiers: ['index']),
            ],
            requirements: ['workoutId' => '[0-9a-f-]{36}', 'index' => '\\d{1,2}'],
            status: 204,
            description: 'Adds an exercise after the last one, with its usual values.',
            input: AddWorkoutExerciseInput::class,
            output: false,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: WorkoutEntryProcessor::class,
        ),
    ],
)]
final readonly class WorkoutExerciseEntry
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $workoutId,
        #[ApiProperty(identifier: true)]
        public int $index,
    ) {
    }
}
