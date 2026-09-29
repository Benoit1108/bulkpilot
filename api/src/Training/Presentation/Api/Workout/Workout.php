<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Training\Application\Workout\ListWorkoutsHandler;
use App\Training\Application\Workout\WorkoutView;

/**
 * A training session (spec §42–46). Ids are chosen by the device and every write is idempotent:
 * the PWA records sets offline and sends them again until they are acknowledged.
 */
#[ApiResource(
    shortName: 'Workout',
    operations: [
        new GetCollection(
            uriTemplate: '/workouts',
            description: 'Finished workouts, the latest first.',
            paginationEnabled: false,
            output: WorkoutSummary::class,
            provider: WorkoutProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'At most '.ListWorkoutsHandler::MAX.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => ListWorkoutsHandler::MAX, 'default' => 20]),
                'before' => new QueryParameter(description: 'Only workouts started before this time (the startedAt of the last one received, for the next page).', schema: ['type' => 'string', 'format' => 'date-time']),
            ],
            name: 'workouts_history',
        ),
        new GetCollection(
            uriTemplate: '/workouts/in-progress',
            description: 'The workout in progress, if any (to resume it after an interruption or on another device).',
            paginationEnabled: false,
            provider: WorkoutProvider::class,
            name: 'workouts_in_progress',
        ),
        new Get(uriTemplate: '/workouts/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'A workout, with the last performance of each exercise.', provider: WorkoutProvider::class),
        new Put(
            uriTemplate: '/workouts/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Starts a workout with this id (sending it again changes nothing): from a program day as the device saw it, or empty. 409 while another one is in progress.',
            input: StartWorkoutInput::class,
            read: false,
            processor: WorkoutProcessor::class,
            name: 'workout_start',
        ),
        new Post(
            uriTemplate: '/workouts/{id}/finish',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'Finishes the workout (only when the user says so); finishing again keeps the first time.',
            input: FinishWorkoutInput::class,
            read: false,
            processor: WorkoutProcessor::class,
            name: 'workout_finish',
        ),
        new Delete(uriTemplate: '/workouts/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Discards a workout started by mistake.', read: false, processor: WorkoutProcessor::class),
    ],
)]
final readonly class Workout
{
    /**
     * @param list<WorkoutExerciseOutput> $exercises
     * @param list<WorkoutSetOutput>      $sets
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['in_progress', 'finished']])]
        public string $status,
        #[ApiProperty(required: true)]
        public ?WorkoutProgramDay $programDay,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $startedAt,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $finishedAt,
        #[ApiProperty(required: true)]
        public array $exercises,
        #[ApiProperty(required: true)]
        public array $sets,
    ) {
    }

    public static function fromView(WorkoutView $view): self
    {
        \assert(null !== $view->id && null !== $view->startedAt);

        return new self(
            $view->id,
            null === $view->finishedAt ? 'in_progress' : 'finished',
            WorkoutProgramDay::of($view),
            $view->startedAt,
            $view->finishedAt,
            array_map(WorkoutExerciseOutput::of(...), $view->exercises),
            array_map(WorkoutSetOutput::of(...), $view->sets),
        );
    }
}
