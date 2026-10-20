<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Builder;

use App\Nutrition\Application\Food\ListRecentFoods;
use App\Nutrition\Domain\Builder\MacroGoal;
use App\Nutrition\Domain\Builder\MealBuilder;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\RecentFood;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class BuildMealHandler implements QueryHandler
{
    /**
     * CIQUAL staples always offered alongside the user's own foods, so that a new user gets
     * proposals too: cooked chicken breast, cooked rice, cooked pasta, egg, semi-skimmed milk,
     * banana, sunflower oil.
     */
    public const array STAPLES = ['36018', '9104', '9811', '22000', '19041', '13005', '17440'];

    public function __construct(
        private NutritionTargetRepository $targets,
        private JournalEntryRepository $entries,
        private MealSplitRepository $mealSplits,
        private FoodRepository $foods,
        private MealBuilder $builder,
    ) {
    }

    public function __invoke(BuildMeal $query): MealBuilderView
    {
        $day = $query->day->setTime(0, 0);
        $target = $this->targets->inForceOn($query->userId, $day);
        if (null === $target) {
            return new MealBuilderView(null, $query->slot, []);
        }

        $targets = $target->targets();
        $entries = $this->entries->ofDay($query->userId, $day);
        $goal = MacroGoal::remainingOf($targets->amounts(), self::totalOf($entries));

        $slot = $query->slot;
        $split = $this->mealSplits->splitOf($query->userId);
        // A meal gets its share of the day, never more than what is left of the day; a meal that
        // is not planned in the split takes whatever is left.
        if (null !== $slot && $split->shareOf($slot) > 0) {
            $ofSlot = array_filter($entries, static fn (JournalEntry $entry): bool => $entry->slot() === $slot);
            $goal = MacroGoal::remainingOf($split->targetOf($targets, $slot), self::totalOf($ofSlot))->within($goal);
        }

        return new MealBuilderView($goal, $slot, array_map(ProposalView::of(...), $this->builder->propose($goal, $this->candidates($query))));
    }

    /**
     * The user's habits first (recent, then their own foods), then the staples.
     *
     * @return list<Food>
     */
    private function candidates(BuildMeal $query): array
    {
        $recentIds = array_map(static fn (RecentFood $recent): FoodId => $recent->foodId, $this->entries->recentFoods($query->userId, ListRecentFoods::LIMIT));
        $recent = $this->foods->visibleAmong($query->userId, $recentIds);
        $candidates = [];
        foreach ([...$recent, ...$this->foods->customFoodsOf($query->userId), ...$this->foods->fromCatalogue(FoodSource::Ciqual, self::STAPLES)] as $food) {
            $candidates[$food->id()->value] ??= $food;
        }

        return array_values($candidates);
    }

    /**
     * @param iterable<JournalEntry> $entries
     */
    private static function totalOf(iterable $entries): NutrientAmounts
    {
        $amounts = [];
        foreach ($entries as $entry) {
            $amounts[] = $entry->amounts();
        }

        return NutrientAmounts::sum($amounts);
    }
}
