<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Calendar\Days;

final readonly class GetNutritionHistoryHandler implements QueryHandler
{
    /** "Everything" starts here: long before any journal. */
    private const string EARLIEST = '2000-01-01';

    public function __construct(private JournalEntryRepository $entries, private NutritionTargetRepository $targets)
    {
    }

    public function __invoke(GetNutritionHistory $query): NutritionHistoryView
    {
        $today = $query->today->setTime(0, 0);
        $from = $query->period->firstDay($today) ?? new \DateTimeImmutable(self::EARLIEST);
        $days = array_values($this->entries->dailyTotals($query->userId, $from, $today->modify('-1 day')));
        usort($days, static fn (DayTotals $a, DayTotals $b): int => $a->day <=> $b->day);

        $weeks = [];
        foreach ($days as $day) {
            $weeks[Days::weekOf($day->day)->format('Y-m-d')][] = $day;
        }

        return new NutritionHistoryView(
            $days,
            array_map(
                static fn (string $week, array $logged): WeekIntake => new WeekIntake(new \DateTimeImmutable($week), self::mean($logged, 'energyKcal'), self::mean($logged, 'protein'), \count($logged)),
                array_keys($weeks),
                array_values($weeks),
            ),
            [] === $days ? null : self::mean($days, 'energyKcal'),
            [] === $days ? null : self::mean($days, 'protein'),
            $this->targets->inForceOn($query->userId, $today)?->targets(),
        );
    }

    /**
     * @param non-empty-list<DayTotals> $days
     * @param 'energyKcal'|'protein'    $nutrient
     */
    private static function mean(array $days, string $nutrient): float
    {
        return round(array_sum(array_map(static fn (DayTotals $day): float => $day->amounts->{$nutrient}, $days)) / \count($days), 1);
    }
}
