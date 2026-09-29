<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

/**
 * A day and its weight: what the trend and goal calculations read.
 */
final readonly class DayWeight
{
    public function __construct(public \DateTimeImmutable $day, public float $kg)
    {
    }

    public static function of(WeighIn $weighIn): self
    {
        return new self($weighIn->day(), $weighIn->weight()->kg);
    }
}
