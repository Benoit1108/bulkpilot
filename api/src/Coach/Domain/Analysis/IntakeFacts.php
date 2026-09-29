<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * What was eaten over a window of days, against the target in force.
 */
final readonly class IntakeFacts
{
    /** A day "on target": within this share of the calories, either way. */
    public const float KCAL_TOLERANCE = 0.10;
    /** A day "on protein": at least this share of the protein target. */
    public const float PROTEIN_SHARE = 0.90;

    /**
     * @param list<array{kcal: float, protein: float}> $days the days with anything logged
     */
    public function __construct(public int $windowDays, public array $days)
    {
    }

    public function logged(): int
    {
        return \count($this->days);
    }

    public function averageKcal(): ?float
    {
        return [] === $this->days ? null : round(array_sum(array_column($this->days, 'kcal')) / \count($this->days));
    }

    public function averageProtein(): ?float
    {
        return [] === $this->days ? null : round(array_sum(array_column($this->days, 'protein')) / \count($this->days));
    }

    public function daysOnKcal(int $targetKcal): int
    {
        return \count(array_filter($this->days, static fn (array $day): bool => abs($day['kcal'] - $targetKcal) <= $targetKcal * self::KCAL_TOLERANCE));
    }

    public function daysOnProtein(int $targetGrams): int
    {
        return \count(array_filter($this->days, static fn (array $day): bool => $day['protein'] >= $targetGrams * self::PROTEIN_SHARE));
    }
}
