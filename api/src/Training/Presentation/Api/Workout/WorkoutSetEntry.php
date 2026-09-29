<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;

/**
 * A set of a workout, sent by the device as soon as it is done (and again until acknowledged).
 */
#[ApiResource(
    shortName: 'WorkoutSet',
    operations: [
        new Put(
            uriTemplate: '/workouts/{workoutId}/sets/{id}',
            // Two separate identifiers, not a composite one.
            uriVariables: [
                'workoutId' => new Link(fromClass: self::class, identifiers: ['workoutId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['workoutId' => '[0-9a-f-]{36}', 'id' => '[0-9a-f-]{36}'],
            status: 204,
            description: 'Records a set, or corrects it (same id). Sending it again changes nothing.',
            input: WorkoutSetInput::class,
            output: false,
            read: false,
            processor: WorkoutEntryProcessor::class,
        ),
        new Delete(
            uriTemplate: '/workouts/{workoutId}/sets/{id}',
            // Two separate identifiers, not a composite one.
            uriVariables: [
                'workoutId' => new Link(fromClass: self::class, identifiers: ['workoutId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['workoutId' => '[0-9a-f-]{36}', 'id' => '[0-9a-f-]{36}'],
            description: 'Removes a set (removing it again changes nothing).',
            read: false,
            processor: WorkoutEntryProcessor::class,
        ),
    ],
)]
final readonly class WorkoutSetEntry
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $workoutId,
        #[ApiProperty(identifier: true)]
        public string $id,
    ) {
    }
}
