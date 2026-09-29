<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

final readonly class TargetFacts
{
    public function __construct(public \DateTimeImmutable $effectiveFrom, public int $energyKcal, public int $proteinGrams)
    {
    }
}
