<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

/**
 * A change of the daily calories the user can accept (spec §66), made against a target.
 */
final readonly class CalorieProposal
{
    public function __construct(public int $fromKcal, public int $byKcal)
    {
    }

    public function toKcal(): int
    {
        return $this->fromKcal + $this->byKcal;
    }

    public function equals(?self $other): bool
    {
        return null !== $other && $this->fromKcal === $other->fromKcal && $this->byKcal === $other->byKcal;
    }
}
