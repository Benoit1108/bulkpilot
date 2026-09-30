<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

/**
 * A milestone (or the target) of the weight goal followed, and the day a weigh-in reached it.
 */
final readonly class ReachedStep
{
    public function __construct(public string $goalId, public float $weightKg, public bool $target, public \DateTimeImmutable $reachedOn)
    {
    }
}
