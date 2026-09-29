<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rest;
use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Prescription\Tempo;

/**
 * An exercise as a program prescribes it: working sets, their target, effort and rest, how
 * its load progresses. Warm-up sets and a drop set after the last one are planned counts: the
 * user logs them as they go (no automatic warm-up, spec §36).
 */
final readonly class PlannedExercise
{
    public const int MAX_SETS = 10;
    public const int MAX_WARMUP_SETS = 5;
    public const float MIN_INCREMENT_KG = 0.25;
    public const float MAX_INCREMENT_KG = 20.0;
    public const int MAX_NOTES_LENGTH = 200;
    /** Sets proposed for an exercise done outside a program. */
    public const int USUAL_SETS = 3;
    public const float USUAL_INCREMENT_KG = 2.5;

    public ?int $targetRir;
    public int $restSeconds;
    public float $incrementKg;
    public ?string $notes;

    public function __construct(
        public ExerciseId $exerciseId,
        public int $sets,
        public RepRange $repRange,
        ?int $targetRir,
        int $restSeconds,
        public ProgressionStrategy $strategy,
        float $incrementKg = 2.5,
        public int $warmupSets = 0,
        public bool $dropSet = false,
        public ?Tempo $tempo = null,
        ?string $notes = null,
    ) {
        if ($sets < 1 || $sets > self::MAX_SETS) {
            throw InvalidPrescription::because(\sprintf('an exercise has 1 to %d working sets', self::MAX_SETS));
        }
        if ($warmupSets < 0 || $warmupSets > self::MAX_WARMUP_SETS) {
            throw InvalidPrescription::because(\sprintf('an exercise has at most %d warm-up sets', self::MAX_WARMUP_SETS));
        }
        if ($incrementKg < self::MIN_INCREMENT_KG || $incrementKg > self::MAX_INCREMENT_KG) {
            throw InvalidPrescription::because('a load increment is between 0.25 and 20 kg');
        }
        $notes = null === $notes ? '' : trim($notes);
        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw InvalidPrescription::because(\sprintf('program notes have at most %d characters', self::MAX_NOTES_LENGTH));
        }

        $this->targetRir = Rir::valid($targetRir);
        $this->restSeconds = Rest::valid($restSeconds);
        $this->incrementKg = round($incrementKg, 2);
        $this->notes = '' === $notes ? null : $notes;
    }

    /**
     * An exercise as it is usually done (its defaults), when no program prescribes it.
     */
    public static function usual(ExerciseId $exerciseId, ExerciseDefaults $defaults): self
    {
        return new self($exerciseId, self::USUAL_SETS, $defaults->repRange, $defaults->targetRir, $defaults->restSeconds, ProgressionStrategy::DoubleProgression, self::USUAL_INCREMENT_KG, tempo: $defaults->tempo);
    }

    public function equals(self $other): bool
    {
        return $this->exerciseId->equals($other->exerciseId)
            && $this->sets === $other->sets
            && $this->repRange->equals($other->repRange)
            && $this->targetRir === $other->targetRir
            && $this->restSeconds === $other->restSeconds
            && $this->strategy === $other->strategy
            && $this->incrementKg === $other->incrementKg
            && $this->warmupSets === $other->warmupSets
            && $this->dropSet === $other->dropSet
            && $this->tempo?->value === $other->tempo?->value
            && $this->notes === $other->notes;
    }
}
