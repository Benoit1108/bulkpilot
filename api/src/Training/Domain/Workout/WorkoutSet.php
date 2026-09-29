<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\Rir;

/**
 * What was done: a load and reps (the load is what is added to the body for a bodyweight
 * exercise), or a duration; the effort felt (RIR) when noted (spec §39).
 */
final readonly class WorkoutSet
{
    public const float MAX_LOAD_KG = 1000.0;
    public const int MAX_REPS = 300;
    public const int MAX_SECONDS = 3600;

    public ?float $loadKg;
    public ?int $rir;

    public function __construct(
        public SetId $id,
        public int $exerciseIndex,
        public SetKind $kind,
        Measurement $measurement,
        ?float $loadKg,
        public ?int $reps,
        public ?int $seconds,
        ?int $rir,
        public \DateTimeImmutable $doneAt,
    ) {
        if ($exerciseIndex < 0) {
            throw InvalidWorkout::because('a set belongs to an exercise of the workout');
        }
        if (null !== $loadKg && ($loadKg < 0 || $loadKg > self::MAX_LOAD_KG)) {
            throw InvalidWorkout::because('a load is between 0 and 1000 kg');
        }
        $recorded = match ($measurement) {
            Measurement::Weight => null !== $loadKg && null !== $reps && null === $seconds,
            Measurement::Bodyweight => null !== $reps && null === $seconds,
            Measurement::Duration => null !== $seconds && null === $reps,
        };
        if (!$recorded) {
            throw InvalidWorkout::because(match ($measurement) {
                Measurement::Weight => 'a set of this exercise records a load and reps', Measurement::Bodyweight => 'a set of this exercise records reps (and an optional added load)', Measurement::Duration => 'a set of this exercise records a duration',
            });
        }
        if (null !== $reps && ($reps < 0 || $reps > self::MAX_REPS)) {
            throw InvalidWorkout::because(\sprintf('a set has 0 to %d reps', self::MAX_REPS));
        }
        if (null !== $seconds && ($seconds < 1 || $seconds > self::MAX_SECONDS)) {
            throw InvalidWorkout::because('a timed set lasts 1 second to 1 hour');
        }

        $this->loadKg = null === $loadKg ? null : round($loadKg, 2);
        $this->rir = Rir::valid($rir);
    }

    /**
     * Load × reps: the volume a set adds (a bodyweight set without added load adds none).
     */
    public function volume(): float
    {
        return ($this->loadKg ?? 0.0) * ($this->reps ?? 0);
    }

    /**
     * @param list<self> $sets
     */
    public static function totalVolume(array $sets): float
    {
        return round(array_sum(array_map(static fn (self $set): float => $set->volume(), $sets)), 1);
    }
}
