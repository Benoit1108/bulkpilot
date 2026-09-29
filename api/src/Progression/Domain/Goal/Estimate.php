<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\WeightTrend;

/**
 * Weeks left to a weight at the current speed ("~17 semaines"), rounded up; or why it cannot be said.
 */
final readonly class Estimate
{
    private function __construct(public EstimateStatus $status, public ?int $weeks)
    {
    }

    public static function toward(float $remainingKg, ?float $kgPerWeek, TrendDirection $direction): self
    {
        $towards = TrendDirection::Gaining === $direction ? $remainingKg : -$remainingKg;

        return match (true) {
            $towards <= 0.0 => new self(EstimateStatus::Reached, null),
            null === $kgPerWeek => new self(EstimateStatus::NotEnoughWeighIns, null),
            abs($kgPerWeek) <= WeightTrend::STABLE_KG_PER_WEEK => new self(EstimateStatus::NotMoving, null),
            ($kgPerWeek > 0) !== ($remainingKg > 0) => new self(EstimateStatus::MovingAway, null),
            default => new self(EstimateStatus::Estimated, (int) ceil($remainingKg / $kgPerWeek)),
        };
    }
}
