<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Weight\WeighIn;

final readonly class WeighInView
{
    public function __construct(public \DateTimeImmutable $day, public float $weightKg, public \DateTimeImmutable $recordedAt)
    {
    }

    public static function of(WeighIn $weighIn): self
    {
        return new self($weighIn->day(), $weighIn->weight()->kg, $weighIn->updatedAt());
    }
}
