<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

final readonly class DayLength
{
    public function __construct(public \DateTimeImmutable $day, public float $cm)
    {
    }
}
