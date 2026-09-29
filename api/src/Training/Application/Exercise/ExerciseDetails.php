<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;

/**
 * An exercise as the user describes it; turned into value objects (and checked) by the use case.
 */
final readonly class ExerciseDetails
{
    /**
     * @param list<Muscle> $secondaryMuscles
     */
    public function __construct(
        public string $name,
        public Muscle $primaryMuscle,
        public array $secondaryMuscles,
        public ExerciseType $type,
        public Equipment $equipment,
        public Measurement $measurement,
        public int $repMin,
        public int $repMax,
        public ?int $targetRir,
        public int $restSeconds,
        public ?string $tempo = null,
        public ?string $description = null,
        public ?string $mediaUrl = null,
    ) {
    }

    public function spec(): ExerciseSpec
    {
        $tempo = null === $this->tempo || '' === trim($this->tempo) ? null : new Tempo($this->tempo);

        return new ExerciseSpec(
            $this->name,
            $this->primaryMuscle,
            $this->secondaryMuscles,
            $this->type,
            $this->equipment,
            $this->measurement,
            new ExerciseDefaults(new RepRange($this->repMin, $this->repMax), $this->targetRir, $this->restSeconds, $tempo),
            $this->description,
            $this->mediaUrl,
        );
    }
}
