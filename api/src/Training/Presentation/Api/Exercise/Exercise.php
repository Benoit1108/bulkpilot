<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;
use App\Training\Application\Exercise\ExerciseView;
use App\Training\Domain\Exercise\Muscle;

/**
 * A movement from the shared library or one of the user's own, with what it is usually done
 * with (proposed when it is added to a program) and the user's standing cue for it.
 */
#[ApiResource(
    shortName: 'Exercise',
    operations: [
        new GetCollection(
            uriTemplate: '/exercises',
            description: 'The exercises the user can use (library and their own, their own first), filtered by name and muscle.',
            paginationEnabled: false,
            provider: ExerciseProvider::class,
            parameters: [
                'q' => new QueryParameter(description: 'Words of the name, accents and case ignored.', schema: ['type' => 'string', 'maxLength' => 100]),
                'muscle' => new QueryParameter(description: 'Only exercises working this muscle (primary or secondary).', schema: ['type' => 'string', 'enum' => ExerciseChoices::MUSCLES]),
            ],
        ),
        new Get(uriTemplate: '/exercises/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'A library exercise, or one of the user\'s own.', provider: ExerciseProvider::class),
        new Post(uriTemplate: '/exercises', description: 'Creates one of the user\'s own exercises.', input: ExerciseInput::class, openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]), processor: ExerciseProcessor::class),
        new Put(uriTemplate: '/exercises/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Replaces one of the user\'s own exercises (library ones are read-only).', input: ExerciseInput::class, read: false, openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]), processor: ExerciseProcessor::class),
        new Delete(uriTemplate: '/exercises/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Deletes one of the user\'s own exercises; programs and history using it keep showing it.', read: false, openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]), processor: ExerciseProcessor::class),
        new Put(
            uriTemplate: '/exercises/{id}/note',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Sets the user\'s standing cue for an exercise (shown every time they do it); blank removes it.',
            input: ExerciseNoteInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: ExerciseProcessor::class,
            name: 'exercise_note',
        ),
    ],
)]
final readonly class Exercise
{
    /**
     * @param list<string> $secondaryMuscles
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['library', 'custom']])]
        public string $source,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::MUSCLES])]
        public string $primaryMuscle,
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ExerciseChoices::MUSCLES]])]
        public array $secondaryMuscles,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::TYPES])]
        public string $type,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::EQUIPMENT])]
        public string $equipment,
        /** What a set records: load and reps, reps with the body's weight (optional added load), or seconds. */
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::MEASUREMENTS])]
        public string $measurement,
        #[ApiProperty(required: true)]
        public ?string $description,
        #[ApiProperty(required: true)]
        public ?string $mediaUrl,
        /** The library's stable key (« barbell_bench_press »), what the app's illustrations are named by; null for the user's own exercises. */
        #[ApiProperty(required: true)]
        public ?string $libraryKey,
        /** Usual target: reps, or seconds for a timed exercise. */
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
        /** The user's standing cue for it. */
        #[ApiProperty(required: true)]
        public ?string $note,
        #[ApiProperty(required: true)]
        public bool $editable,
    ) {
    }

    public static function fromView(ExerciseView $view): self
    {
        $defaults = $view->defaults;

        return new self(
            $view->id,
            $view->source->value,
            $view->name,
            $view->primaryMuscle->value,
            array_map(static fn (Muscle $muscle): string => $muscle->value, $view->secondaryMuscles),
            $view->type->value,
            $view->equipment->value,
            $view->measurement->value,
            $view->description,
            $view->mediaUrl,
            $view->libraryKey,
            $defaults->repRange->min,
            $defaults->repRange->max,
            $defaults->targetRir,
            $defaults->restSeconds,
            $defaults->tempo?->value,
            $view->note,
            $view->editable,
        );
    }
}
