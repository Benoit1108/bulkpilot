<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Workout\WorkoutSet;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Load and reps, reps (and an optional added load) or seconds, depending on the exercise.
 */
final class WorkoutSetInput
{
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $exerciseIndex = null;

    #[Assert\Choice(choices: WorkoutSetOutput::KINDS)]
    public string $kind = 'normal';

    #[Assert\Range(min: 0, max: WorkoutSet::MAX_LOAD_KG)]
    public ?float $loadKg = null;

    #[Assert\Range(min: 0, max: WorkoutSet::MAX_REPS)]
    public ?int $reps = null;

    #[Assert\Range(min: 1, max: WorkoutSet::MAX_SECONDS)]
    public ?int $seconds = null;

    #[Assert\Range(min: 0, max: Rir::MAX)]
    public ?int $rir = null;

    /** When it was done, by the device's clock. */
    #[Assert\NotNull]
    public ?\DateTimeImmutable $doneAt = null;
}
