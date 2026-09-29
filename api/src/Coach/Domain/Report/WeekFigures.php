<?php

declare(strict_types=1);

namespace App\Coach\Domain\Report;

/**
 * The figures of a week (spec §59), computed by Symfony and kept as they were.
 *
 * @phpstan-type WeekFiguresArray array{startKg: float|null, endKg: float|null, kgPerWeek: float|null, paceKgPerWeek: float|null, averageKcal: float|null, averageProtein: float|null, loggedDays: int, targetKcal: int|null, targetProtein: int|null, sessions: int, plannedSessions: int|null, records: int}
 */
final readonly class WeekFigures
{
    public function __construct(
        /** The 7-day mean at the end of the week before, and at the end of this one. */
        public ?float $startKg,
        public ?float $endKg,
        public ?float $kgPerWeek,
        public ?float $paceKgPerWeek,
        public ?float $averageKcal,
        public ?float $averageProtein,
        public int $loggedDays,
        public ?int $targetKcal,
        public ?int $targetProtein,
        public int $sessions,
        public ?int $plannedSessions,
        public int $records,
    ) {
    }

    public function changeKg(): ?float
    {
        return null === $this->startKg || null === $this->endKg ? null : round($this->endKg - $this->startKg, 1);
    }

    /** Nothing was logged, weighed or trained that week. */
    public function isEmpty(): bool
    {
        return null === $this->endKg && 0 === $this->loggedDays && 0 === $this->sessions;
    }

    /**
     * @return WeekFiguresArray
     */
    public function toArray(): array
    {
        return [
            'startKg' => $this->startKg,
            'endKg' => $this->endKg,
            'kgPerWeek' => $this->kgPerWeek,
            'paceKgPerWeek' => $this->paceKgPerWeek,
            'averageKcal' => $this->averageKcal,
            'averageProtein' => $this->averageProtein,
            'loggedDays' => $this->loggedDays,
            'targetKcal' => $this->targetKcal,
            'targetProtein' => $this->targetProtein,
            'sessions' => $this->sessions,
            'plannedSessions' => $this->plannedSessions,
            'records' => $this->records,
        ];
    }

    /**
     * @param WeekFiguresArray $figures
     */
    public static function fromArray(array $figures): self
    {
        return new self(...$figures);
    }
}
