<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InvalidFavoriteMeal;
use App\Nutrition\Domain\Exception\InvalidRecipe;
use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeComposition;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    private UserId $owner;
    private Food $rice;
    private Food $chicken;
    private \DateTimeImmutable $now;
    private int $entries = 0;

    protected function setUp(): void
    {
        $this->owner = new UserId('00000000-0000-7000-8000-000000000001');
        $this->rice = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000f1'), FoodSource::Ciqual, '9100', 'Riz blanc, cru', null, new NutritionFacts(350, 7, 78, 1));
        $this->chicken = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000f2'), FoodSource::Ciqual, '36017', 'Poulet, filet, cru', null, new NutritionFacts(110, 23, 0, 2));
        $this->now = new \DateTimeImmutable('2026-09-28 10:00');
    }

    public function testTheCompositionIsTheSumOfItsIngredientsSplitIntoServings(): void
    {
        $recipe = $this->recipe(null);

        $composition = RecipeComposition::of($recipe, $this->foods());

        self::assertEqualsWithDelta(300 * 3.5 + 400 * 1.1, $composition->total->energyKcal, 0.001); // 1490
        self::assertSame(700.0, $composition->totalGrams, 'raw weight when the dish was not weighed');
        self::assertSame(175.0, $composition->servingGrams);
        self::assertEqualsWithDelta(372.5, $composition->perServing->energyKcal, 0.001);
        self::assertEqualsWithDelta(1490 / 7, $composition->per100Grams->energyKcal, 0.001);
        self::assertEqualsWithDelta((21 + 92) / 4, $composition->perServing->protein, 0.001);
    }

    public function testTheCookedWeightGivesServingsAndPer100GramsOfTheCookedDish(): void
    {
        // Rice absorbs water: 700 g raw become 1100 g cooked; the calories do not change.
        $composition = RecipeComposition::of($this->recipe(1100), $this->foods());

        self::assertSame(1100.0, $composition->totalGrams);
        self::assertSame(275.0, $composition->servingGrams);
        self::assertEqualsWithDelta(1490 / 11, $composition->per100Grams->energyKcal, 0.001);
        self::assertEqualsWithDelta(372.5, $composition->perServing->energyKcal, 0.001);
    }

    public function testAMissingFoodIsReported(): void
    {
        $this->expectException(InvalidRecipe::class);

        RecipeComposition::of($this->recipe(null), [$this->rice->id()->value => $this->rice]);
    }

    public function testARecipeIsEditedDeletedAndKeptForItsOwner(): void
    {
        $recipe = $this->recipe(null);
        $other = new UserId('00000000-0000-7000-8000-000000000002');

        $recipe->edit(' Riz poulet ', 2, 900.04, [new Ingredient($this->rice->id(), 250)]);
        self::assertSame('Riz poulet', $recipe->name());
        self::assertSame(2, $recipe->servings());
        self::assertSame(900.0, $recipe->cookedWeightGrams());
        self::assertCount(1, $recipe->ingredients());
        self::assertFalse($recipe->isVisibleTo($other));
        self::assertSame($this->owner, $recipe->ownerId());
        self::assertSame($this->now, $recipe->createdAt());

        $recipe->delete($this->now);
        self::assertFalse($recipe->isVisibleTo($this->owner));
        self::assertTrue($recipe->belongsTo($this->owner), 'still usable by favourites');
    }

    /**
     * @return iterable<string, array{string, int, ?float, int}>
     */
    public static function invalidRecipes(): iterable
    {
        yield 'blank name' => [' ', 4, null, 1];
        yield 'long name' => [str_repeat('a', 201), 4, null, 1];
        yield 'no serving' => ['Riz', 0, null, 1];
        yield 'too many servings' => ['Riz', 51, null, 1];
        yield 'no cooked weight' => ['Riz', 4, 0, 1];
        yield 'too heavy' => ['Riz', 4, 20001, 1];
        yield 'no ingredient' => ['Riz', 4, null, 0];
        yield 'too many ingredients' => ['Riz', 4, null, 51];
    }

    #[DataProvider('invalidRecipes')]
    public function testInvalidRecipesAreRejected(string $name, int $servings, ?float $cooked, int $ingredients): void
    {
        $this->expectException(InvalidRecipe::class);

        Recipe::create(new RecipeId('00000000-0000-7000-8000-00000000000a'), $this->owner, $name, $servings, $cooked, array_fill(0, $ingredients, new Ingredient($this->rice->id(), 100)), $this->now);
    }

    public function testIngredientsArePlausible(): void
    {
        self::assertSame(120.1, new Ingredient($this->rice->id(), 120.06)->grams);

        $this->expectException(InvalidRecipe::class);
        new Ingredient($this->rice->id(), 0);
    }

    public function testAnEntryLoggedFromARecipeFreezesItsValues(): void
    {
        $recipe = $this->recipe(null);
        $food = LoggedFood::snapshotOfRecipe($recipe, RecipeComposition::of($recipe, $this->foods()));

        self::assertNull($food->foodId);
        self::assertSame($recipe->id(), $food->recipeId);
        self::assertSame('Riz au poulet', $food->name);
        self::assertSame(FoodState::NotApplicable, $food->state);
        self::assertEqualsWithDelta(1490 / 7, $food->per100Grams->energyKcal, 0.001);
    }

    public function testAFavouriteIsMadeFromTheFoodsAndRecipesOfAMeal(): void
    {
        $recipe = $this->recipe(null);
        $entries = [
            $this->entry(LoggedFood::snapshotOf($this->rice), 80),
            $this->entry(LoggedFood::snapshotOfRecipe($recipe, RecipeComposition::of($recipe, $this->foods())), 350),
            $this->entry(new LoggedFood(null, 'Disparu', FoodState::Raw, $this->rice->facts()->reference()), 10),
        ];

        $favorite = FavoriteMeal::fromEntries(new FavoriteMealId('00000000-0000-7000-8000-0000000000fa'), $this->owner, ' Déjeuner type ', $entries, $this->now);

        self::assertSame('Déjeuner type', $favorite->name());
        self::assertCount(2, $favorite->items(), 'an entry that no longer knows its source is left out');
        self::assertSame($this->rice->id(), $favorite->items()[0]->foodId);
        self::assertSame($recipe->id(), $favorite->items()[1]->recipeId);
        self::assertSame(350.0, $favorite->items()[1]->grams);
        self::assertTrue($favorite->belongsTo($this->owner));
        self::assertSame($this->owner, $favorite->ownerId());
        self::assertSame($this->now, $favorite->createdAt());

        $favorite->rename('Midi');
        self::assertSame('Midi', $favorite->name());
    }

    public function testAFavouriteNeedsSomethingToLog(): void
    {
        $this->expectException(InvalidFavoriteMeal::class);

        FavoriteMeal::fromEntries(new FavoriteMealId('00000000-0000-7000-8000-0000000000fa'), $this->owner, 'Vide', [], $this->now);
    }

    /**
     * @return iterable<string, array{?string, ?string, float}>
     */
    public static function invalidItems(): iterable
    {
        yield 'neither' => [null, null, 100];
        yield 'both' => ['00000000-0000-7000-8000-0000000000f1', '00000000-0000-7000-8000-00000000000a', 100];
        yield 'no weight' => ['00000000-0000-7000-8000-0000000000f1', null, 0];
    }

    #[DataProvider('invalidItems')]
    public function testInvalidItemsAreRejected(?string $foodId, ?string $recipeId, float $grams): void
    {
        $this->expectException(InvalidFavoriteMeal::class);

        new FavoriteItem(null === $foodId ? null : new FoodId($foodId), null === $recipeId ? null : new RecipeId($recipeId), $grams);
    }

    public function testAFavouriteNameIsPlausible(): void
    {
        $this->expectException(InvalidFavoriteMeal::class);

        FavoriteMeal::create(new FavoriteMealId('00000000-0000-7000-8000-0000000000fa'), $this->owner, str_repeat('a', 101), [new FavoriteItem($this->rice->id(), null, 80)], $this->now);
    }

    private function recipe(?float $cooked): Recipe
    {
        return Recipe::create(
            new RecipeId('00000000-0000-7000-8000-00000000000a'),
            $this->owner,
            'Riz au poulet',
            4,
            $cooked,
            [new Ingredient($this->rice->id(), 300), new Ingredient($this->chicken->id(), 400)],
            $this->now,
        );
    }

    /**
     * @return array<string, Food>
     */
    private function foods(): array
    {
        return [$this->rice->id()->value => $this->rice, $this->chicken->id()->value => $this->chicken];
    }

    private function entry(LoggedFood $food, float $grams): JournalEntry
    {
        return JournalEntry::log(new JournalEntryId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->entries)), $this->owner, $this->now, MealSlot::Lunch, $food, $grams, $this->now);
    }
}
