<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use Symfony\Component\Validator\Constraints as Assert;

final class StartWorkoutInput
{
    /** When the user started, by the device's clock (it may have been offline). */
    #[Assert\NotNull]
    public ?\DateTimeImmutable $startedAt = null;

    /** Empty for a free workout. */
    #[Assert\Uuid]
    public ?string $programId = null;

    /** The version the device saw (the current one when left out). */
    #[Assert\Positive]
    public ?int $versionNumber = null;

    #[Assert\Range(min: 0, max: 6)]
    public int $dayIndex = 0;
}
