<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Application\Journal\JournalWriter;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalDays;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Tests\Support\Shared\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Nutrition use cases on in-memory adapters.
 */
abstract class NutritionTestCase extends TestCase
{
    protected const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    protected const string SAM = '00000000-0000-7000-8000-000000000511';

    protected InMemoryFoodRepository $foods;
    protected InMemoryJournalEntryRepository $entries;
    protected InMemoryNutritionTargetRepository $targets;
    protected InMemoryMealSplitRepository $mealSplits;
    protected InMemoryRecipeRepository $recipes;
    protected InMemoryFavoriteMealRepository $favorites;
    protected SpyEventBus $events;
    protected SequentialUuidGenerator $uuids;
    protected MockClock $clock;
    protected UserId $alex;
    protected UserId $sam;

    protected function setUp(): void
    {
        $this->foods = new InMemoryFoodRepository();
        $this->entries = new InMemoryJournalEntryRepository();
        $this->targets = new InMemoryNutritionTargetRepository();
        $this->mealSplits = new InMemoryMealSplitRepository();
        $this->recipes = new InMemoryRecipeRepository();
        $this->favorites = new InMemoryFavoriteMealRepository();
        $this->events = new SpyEventBus();
        $this->uuids = new SequentialUuidGenerator();
        $this->clock = new MockClock('2026-09-28 10:00:00');
        $this->alex = new UserId(self::ALEX);
        $this->sam = new UserId(self::SAM);
    }

    /**
     * Raw rice: 356 kcal, 7.4 g protein, 78.6 g carbohydrate, 0.7 g fat per 100 g.
     */
    protected function rice(): Food
    {
        return $this->catalogueFood('9100', 'Riz blanc, cru', new NutritionFacts(356, 7.4, 78.6, 0.7, 1.4));
    }

    protected function catalogueFood(string $reference, string $name, NutritionFacts $facts): Food
    {
        $food = Food::fromCatalogue(new FoodId($this->uuids->generate()), FoodSource::Ciqual, $reference, $name, 'Céréales', $facts);
        $this->foods->save($food);

        return $food;
    }

    protected function writer(): JournalWriter
    {
        return new JournalWriter($this->entries, new JournalDays(), $this->clock);
    }

    protected function day(string $day = '2026-09-28'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day);
    }
}
