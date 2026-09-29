<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

/**
 * The next weight to reach on the way (a milestone, or the target): what is left, and how long at the current pace.
 */
final readonly class NextStep
{
    public function __construct(public StepProgress $step, public float $remainingKg, public Estimate $estimate)
    {
    }
}
