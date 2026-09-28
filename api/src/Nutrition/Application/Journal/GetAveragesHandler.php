<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Application\Target\TargetView;
use App\Nutrition\Domain\Exception\InvalidPeriod;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetAveragesHandler implements QueryHandler
{
    public function __construct(private JournalEntryRepository $entries, private NutritionTargetRepository $targets)
    {
    }

    public function __invoke(GetAverages $query): AveragesView
    {
        if (!\in_array($query->days, GetAverages::PERIODS, true)) {
            throw InvalidPeriod::because('averages are over 7 or 30 days');
        }
        $today = $query->today->setTime(0, 0);
        $totals = $this->entries->dailyTotals($query->userId, $today->modify(\sprintf('-%d days', $query->days)), $today->modify('-1 day'));
        $target = $this->targets->inForceOn($query->userId, $today);

        return new AveragesView(
            $query->days,
            \count($totals),
            [] === $totals ? null : NutrientAmounts::sum(array_map(static fn (DayTotals $day): NutrientAmounts => $day->amounts, $totals))->scale(1 / \count($totals)),
            null === $target ? null : TargetView::of($target),
        );
    }
}
