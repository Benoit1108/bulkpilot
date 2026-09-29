<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Workout\ExerciseInWorkoutView;
use App\Training\Presentation\Api\Exercise\ExerciseChoices;

final readonly class WorkoutExerciseOutput
{
    /**
     * @param list<RecordOutput> $records
     * @param list<RecordOutput> $newRecords
     */
    public function __construct(
        /** Position in the workout: what sets refer to. */
        #[ApiProperty(required: true)]
        public int $index,
        #[ApiProperty(required: true)]
        public string $exerciseId,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ExerciseChoices::MEASUREMENTS])]
        public string $measurement,
        /** Exercises sharing a block are a superset. */
        #[ApiProperty(required: true)]
        public int $block,
        /** Added during the workout, not planned by the program. */
        #[ApiProperty(required: true)]
        public bool $added,
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
        #[ApiProperty(required: true)]
        public bool $dropSet,
        /** The program's instruction for this exercise. */
        #[ApiProperty(required: true)]
        public ?string $programNotes,
        /** The user's standing cue for the exercise. */
        #[ApiProperty(required: true)]
        public ?string $note,
        #[ApiProperty(required: true)]
        public ?PreviousOutput $previous,
        #[ApiProperty(required: true)]
        public ?SuggestionOutput $suggestion,
        /** Bests before this workout. */
        #[ApiProperty(required: true)]
        public array $records,
        /** Records this workout beat so far. */
        #[ApiProperty(required: true)]
        public array $newRecords,
    ) {
    }

    public static function of(ExerciseInWorkoutView $view): self
    {
        $exercise = $view->exercise;
        $planned = $exercise->planned;

        return new self(
            $view->index,
            $exercise->exerciseId->value,
            $exercise->name,
            $exercise->measurement->value,
            $exercise->block,
            $exercise->added,
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
            $view->note,
            PreviousOutput::of($view->previous),
            SuggestionOutput::of($view->suggestion),
            array_values(array_map(RecordOutput::of(...), $view->records)),
            array_map(RecordOutput::of(...), $view->newRecords),
        );
    }
}
