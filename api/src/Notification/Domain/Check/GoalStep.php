<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

final readonly class GoalStep
{
    public function __construct(public string $goalId, public float $weightKg, public bool $target, public \DateTimeImmutable $reachedOn)
    {
    }
}
