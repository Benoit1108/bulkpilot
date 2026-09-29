<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

/**
 * A milestone (or the target) and the first day a weigh-in reached it, if one did.
 */
final readonly class StepProgress
{
    public function __construct(public float $kg, public bool $target, public ?\DateTimeImmutable $reachedOn)
    {
    }
}
