<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Favorite\DeleteFavoriteMeal;
use App\Nutrition\Application\Favorite\DeleteFavoriteMealHandler;
use App\Nutrition\Application\Favorite\FavoriteItems;
use App\Nutrition\Application\Favorite\ListFavoriteMeals;
use App\Nutrition\Application\Favorite\ListFavoriteMealsHandler;
use App\Nutrition\Application\Favorite\LogFavoriteMeal;
use App\Nutrition\Application\Favorite\LogFavoriteMealHandler;
use App\Nutrition\Application\Favorite\SaveFavoriteMeal;
use App\Nutrition\Application\Favorite\SaveFavoriteMealHandler;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\CreateCustomFoodHandler;
use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Application\Food\DeleteCustomFood;
use App\Nutrition\Application\Food\DeleteCustomFoodHandler;
use App\Nutrition\Application\Journal\DayView;
use App\Nutrition\Application\Journal\GetDay;
use App\Nutrition\Application\Journal\GetDayHandler;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Journal\LogRecipe;
use App\Nutrition\Application\Journal\LogRecipeHandler;
use App\Nutrition\Application\Recipe\Compositions;
use App\Nutrition\Application\Recipe\CreateRecipe;
use App\Nutrition\Application\Recipe\CreateRecipeHandler;
use App\Nutrition\Application\Recipe\DeleteRecipe;
use App\Nutrition\Application\Recipe\DeleteRecipeHandler;
use App\Nutrition\Application\Recipe\EditRecipe;
use App\Nutrition\Application\Recipe\EditRecipeHandler;
use App\Nutrition\Application\Recipe\GetRecipe;
use App\Nutrition\Application\Recipe\GetRecipeHandler;
use App\Nutrition\Application\Recipe\ListRecipes;
use App\Nutrition\Application\Recipe\ListRecipesHandler;
use App\Nutrition\Application\Recipe\RecipeDetails;
use App\Nutrition\Domain\Exception\FavoriteMealNotFound;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Exception\RecipeNotFound;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class RecipesAndFavoritesTest extends NutritionTestCase
{
    private const string RECIPE = '00000000-0000-7000-8000-0000000000aa';
    private const string SKYR = '00000000-0000-7000-8000-0000000000bb';
    private const string FAVORITE = '00000000-0000-7000-8000-0000000000cc';

    private Compositions $compositions;
    private string $rice;
    private string $chicken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compositions = new Compositions($this->foods);
        $this->rice = $this->catalogueFood('9100', 'Riz blanc, cru', new NutritionFacts(350, 7, 78, 1))->id()->value;
        $this->chicken = $this->catalogueFood('36017', 'Poulet, filet, cru', new NutritionFacts(110, 23, 0, 2))->id()->value;
    }

    public function testARecipeIsCreatedWithItsCompositionAndListed(): void
    {
        $this->createRecipe(1100);

        $view = new GetRecipeHandler($this->recipes, $this->compositions)(new GetRecipe($this->alex, new RecipeId(self::RECIPE)));
        self::assertSame('Riz au poulet', $view->name);
        self::assertSame(['Riz blanc, cru', 'Poulet, filet, cru'], array_map(static fn ($ingredient): string => $ingredient->name, $view->ingredients));
        self::assertEqualsWithDelta(1050.0, $view->ingredients[0]->amounts->energyKcal, 0.001);
        self::assertTrue($view->ingredients[0]->available);
        self::assertSame(275.0, $view->composition->servingGrams);
        self::assertCount(1, new ListRecipesHandler($this->recipes, $this->foods)(new ListRecipes($this->alex)));
        self::assertSame([], new ListRecipesHandler($this->recipes, $this->foods)(new ListRecipes($this->sam)));
    }

    public function testIngredientsMustBeFoodsTheUserCanUse(): void
    {
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::SKYR), $this->sam, new CustomFoodDetails('Skyr', null, FoodState::NotApplicable, 63, 11, 4, 0.2)));

        $this->expectException(FoodNotFound::class);
        new CreateRecipeHandler($this->recipes, $this->compositions, $this->clock)(new CreateRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails('Volé', 1, null, [['foodId' => self::SKYR, 'grams' => 100.0]])));
    }

    public function testARecipeIsEditedThenDeletedButItsEntriesStay(): void
    {
        $this->createRecipe(null);
        $this->logRecipe(350);

        new EditRecipeHandler($this->recipes, $this->compositions)(new EditRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails('Riz nature', 2, null, [['foodId' => $this->rice, 'grams' => 200.0]])));
        self::assertSame('Riz nature', new GetRecipeHandler($this->recipes, $this->compositions)(new GetRecipe($this->alex, new RecipeId(self::RECIPE)))->name);

        new DeleteRecipeHandler($this->recipes, $this->clock)(new DeleteRecipe(new RecipeId(self::RECIPE), $this->alex));
        self::assertSame([], new ListRecipesHandler($this->recipes, $this->foods)(new ListRecipes($this->alex)));

        $entry = $this->journal()->meals[1]->entries[0];
        self::assertSame('Riz au poulet', $entry->name, 'logged under its name of the day');
        self::assertEqualsWithDelta(1490 / 7 * 3.5, $entry->amounts->energyKcal, 0.001);

        $this->expectException(RecipeNotFound::class);
        $this->logRecipe(100);
    }

    public function testSomeoneElsesRecipeIsNotFound(): void
    {
        $this->createRecipe(null);

        $this->expectException(RecipeNotFound::class);
        new GetRecipeHandler($this->recipes, $this->compositions)(new GetRecipe($this->sam, new RecipeId(self::RECIPE)));
    }

    public function testADeletedCustomFoodCannotJoinARecipe(): void
    {
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::SKYR), $this->alex, new CustomFoodDetails('Skyr', null, FoodState::NotApplicable, 63, 11, 4, 0.2)));
        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::SKYR), $this->alex));
        $this->createRecipe(null);

        try {
            new CreateRecipeHandler($this->recipes, $this->compositions, $this->clock)(new CreateRecipe(new RecipeId('00000000-0000-7000-8000-0000000000ab'), $this->alex, new RecipeDetails('Bol', 1, null, [['foodId' => self::SKYR, 'grams' => 200.0]])));
            self::fail('a deleted food is not offered for new recipes');
        } catch (FoodNotFound) {
        }

        $this->expectException(FoodNotFound::class);
        new EditRecipeHandler($this->recipes, $this->compositions)(new EditRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails('Riz au poulet', 4, null, [['foodId' => $this->rice, 'grams' => 300.0], ['foodId' => self::SKYR, 'grams' => 100.0]])));
    }

    public function testADeletedCustomFoodStillCountsInItsRecipes(): void
    {
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::SKYR), $this->alex, new CustomFoodDetails('Skyr', null, FoodState::NotApplicable, 63, 11, 4, 0.2)));
        new CreateRecipeHandler($this->recipes, $this->compositions, $this->clock)(new CreateRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails('Bol', 1, null, [['foodId' => self::SKYR, 'grams' => 200.0]])));
        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::SKYR), $this->alex));

        $view = new GetRecipeHandler($this->recipes, $this->compositions)(new GetRecipe($this->alex, new RecipeId(self::RECIPE)));

        self::assertFalse($view->ingredients[0]->available);
        self::assertEqualsWithDelta(126.0, $view->composition->total->energyKcal, 0.001);

        // The recipe can still be edited around it.
        new EditRecipeHandler($this->recipes, $this->compositions)(new EditRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails('Grand bol', 2, null, [['foodId' => self::SKYR, 'grams' => 300.0]])));
        self::assertSame('Grand bol', new GetRecipeHandler($this->recipes, $this->compositions)(new GetRecipe($this->alex, new RecipeId(self::RECIPE)))->name);
    }

    public function testAMealIsSavedAsAFavouriteThenLoggedAgainInOneGo(): void
    {
        $this->createRecipe(null);
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, new FoodId($this->rice), $this->day('2026-09-27'), MealSlot::Lunch, 80));
        $this->logRecipe(350, '2026-09-27');
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, new FoodId($this->chicken), $this->day('2026-09-27'), MealSlot::Dinner, 200));

        new SaveFavoriteMealHandler($this->entries, $this->favorites, $this->clock)(new SaveFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->alex, 'Midi', $this->day('2026-09-27'), MealSlot::Lunch));

        $listed = new ListFavoriteMealsHandler($this->favorites, $this->items())(new ListFavoriteMeals($this->alex));
        self::assertCount(1, $listed);
        self::assertSame(['Riz blanc, cru', 'Riz au poulet'], array_map(static fn ($item): string => $item->name, $listed[0]->items));
        self::assertTrue($listed[0]->items[1]->recipe);
        self::assertEqualsWithDelta(280 + 1490 / 7 * 3.5, $listed[0]->totals->energyKcal, 0.001);
        self::assertSame([], new ListFavoriteMealsHandler($this->favorites, $this->items())(new ListFavoriteMeals($this->sam)));

        $this->logFavorite('2026-09-28', MealSlot::Dinner);

        $dinner = $this->journal()->meals[4];
        self::assertCount(2, $dinner->entries);
        self::assertEqualsWithDelta(280 + 1490 / 7 * 3.5, $dinner->totals->energyKcal, 0.001);
    }

    public function testAFavouriteIsPrivateAndDeletable(): void
    {
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, new FoodId($this->rice), $this->day(), MealSlot::Breakfast, 80));
        new SaveFavoriteMealHandler($this->entries, $this->favorites, $this->clock)(new SaveFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->alex, 'Matin', $this->day(), MealSlot::Breakfast));

        try {
            new LogFavoriteMealHandler($this->favorites, $this->items(), $this->writer(), $this->uuids)(new LogFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->sam, $this->day(), MealSlot::Lunch));
            self::fail('someone else\'s favourite is not found');
        } catch (FavoriteMealNotFound) {
        }

        new DeleteFavoriteMealHandler($this->favorites)(new DeleteFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->alex));
        self::assertSame([], $this->favorites->favorites);
    }

    public function testAFavouriteKeepsWorkingWhenItsCustomFoodIsDeleted(): void
    {
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::SKYR), $this->alex, new CustomFoodDetails('Skyr', null, FoodState::NotApplicable, 63, 11, 4, 0.2)));
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, new FoodId(self::SKYR), $this->day(), MealSlot::Snack, 150));
        new SaveFavoriteMealHandler($this->entries, $this->favorites, $this->clock)(new SaveFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->alex, 'Collation', $this->day(), MealSlot::Snack));
        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::SKYR), $this->alex));

        $this->logFavorite('2026-09-28', MealSlot::Gainer);

        self::assertSame('Skyr', $this->journal()->meals[3]->entries[0]->name);
    }

    private function createRecipe(?float $cooked): void
    {
        new CreateRecipeHandler($this->recipes, $this->compositions, $this->clock)(new CreateRecipe(new RecipeId(self::RECIPE), $this->alex, new RecipeDetails(
            'Riz au poulet',
            4,
            $cooked,
            [['foodId' => $this->rice, 'grams' => 300.0], ['foodId' => $this->chicken, 'grams' => 400.0]],
        )));
    }

    private function logRecipe(float $grams, string $day = '2026-09-28'): void
    {
        new LogRecipeHandler($this->recipes, $this->compositions, $this->writer())(
            new LogRecipe(new JournalEntryId($this->uuids->generate()), $this->alex, new RecipeId(self::RECIPE), $this->day($day), MealSlot::Lunch, $grams),
        );
    }

    private function logFavorite(string $day, MealSlot $slot): void
    {
        new LogFavoriteMealHandler($this->favorites, $this->items(), $this->writer(), $this->uuids)(
            new LogFavoriteMeal(new FavoriteMealId(self::FAVORITE), $this->alex, $this->day($day), $slot),
        );
    }

    private function journal(): DayView
    {
        return new GetDayHandler($this->entries, $this->targets, $this->mealSplits)(new GetDay($this->alex, $this->day()));
    }

    private function items(): FavoriteItems
    {
        return new FavoriteItems($this->foods, $this->recipes, $this->compositions);
    }
}
