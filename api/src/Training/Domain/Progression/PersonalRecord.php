<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

use App\Training\Domain\Workout\WorkoutId;

final readonly class PersonalRecord
{
    /**
     * @param float      $value  kg, reps or seconds depending on the kind
     * @param float|null $loadKg the load, for a record of reps at a load
     */
    public function __construct(
        public RecordKind $kind,
        public float $value,
        public \DateTimeImmutable $date,
        public WorkoutId $workoutId,
        public ?float $loadKg = null,
    ) {
    }

    /**
     * What a record competes with: its kind, and its load for reps at a load.
     */
    public function key(): string
    {
        return $this->kind->value.(null === $this->loadKg ? '' : '@'.$this->loadKg);
    }
}
