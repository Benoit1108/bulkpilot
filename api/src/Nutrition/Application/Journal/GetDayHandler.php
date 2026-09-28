<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Application\Target\TargetView;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetDayHandler implements QueryHandler
{
    public function __construct(
        private JournalEntryRepository $entries,
        private NutritionTargetRepository $targets,
        private MealSplitRepository $mealSplits,
    ) {
    }

    public function __invoke(GetDay $query): DayView
    {
        $day = $query->day->setTime(0, 0);
        $entries = $this->entries->ofDay($query->userId, $day);
        $target = $this->targets->inForceOn($query->userId, $day);
        $split = $this->mealSplits->splitOf($query->userId);

        $meals = [];
        foreach (MealSlot::cases() as $slot) {
            $ofSlot = array_values(array_filter($entries, static fn (JournalEntry $entry): bool => $entry->slot() === $slot));
            $share = $split->shareOf($slot);
            $meals[] = new MealView(
                $slot,
                $share,
                null === $target ? null : (int) round($split->targetOf($target->targets(), $slot)->energyKcal),
                NutrientAmounts::sum(array_map(static fn (JournalEntry $entry): NutrientAmounts => $entry->amounts(), $ofSlot)),
                array_map(EntryView::of(...), $ofSlot),
            );
        }

        return new DayView(
            $day,
            NutrientAmounts::sum(array_map(static fn (MealView $meal): NutrientAmounts => $meal->totals, $meals)),
            null === $target ? null : TargetView::of($target),
            $meals,
        );
    }
}
