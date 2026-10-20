<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseSource;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;

final readonly class ExerciseView
{
    /**
     * @param list<Muscle> $secondaryMuscles
     * @param string|null  $note             the viewer's standing cue for it
     */
    public function __construct(
        public string $id,
        public ExerciseSource $source,
        public string $name,
        public Muscle $primaryMuscle,
        public array $secondaryMuscles,
        public ExerciseType $type,
        public Equipment $equipment,
        public Measurement $measurement,
        public ?string $description,
        public ?string $mediaUrl,
        public ?string $libraryKey,
        public ExerciseDefaults $defaults,
        public ?string $note,
        public bool $editable,
    ) {
    }

    public static function of(Exercise $exercise, ?ExerciseNote $note, UserId $viewer): self
    {
        return new self(
            $exercise->id()->value,
            $exercise->source(),
            $exercise->name(),
            $exercise->primaryMuscle(),
            $exercise->secondaryMuscles(),
            $exercise->type(),
            $exercise->equipment(),
            $exercise->measurement(),
            $exercise->description(),
            $exercise->mediaUrl(),
            $exercise->libraryKey(),
            $exercise->defaults(),
            $note?->text(),
            $exercise->isEditableBy($viewer),
        );
    }
}
