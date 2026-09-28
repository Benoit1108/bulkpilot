<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class RecipesAndFavoritesTest extends NutritionApiTestCase
{
    public function testARecipeIsCreatedWithItsCompositionThenLoggedByTheServing(): void
    {
        $this->register();
        $recipe = $this->createRecipe();

        self::assertSame(4, self::int($recipe, 'servings'));
        self::assertSame(275.0, self::number($recipe, 'servingGrams'));
        self::assertSame('Riz blanc, cru', self::string($recipe, 'ingredients.0.name'));
        self::assertTrue(self::at($recipe, 'ingredients.1.available'));
        $perServing = self::number($recipe, 'perServing.energyKcal');
        self::assertEqualsWithDelta(self::number($recipe, 'total.energyKcal') / 4, $perServing, 0.1);

        $day = $this->call('POST', '/api/nutrition/entries', ['recipeId' => self::string($recipe, 'id'), 'day' => $this->today, 'slot' => 'lunch', 'grams' => 275])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Riz au poulet', self::string($day, 'meals.1.entries.0.name'));
        self::assertNull(self::at($day, 'meals.1.entries.0.foodId'));
        self::assertEqualsWithDelta($perServing, self::number($day, 'totals.energyKcal'), 0.2);

        self::assertCount(1, $this->getJson('/api/recipes'));
    }

    public function testRecipesArePrivateEditableAndDeletable(): void
    {
        $this->register();
        $id = self::string($this->createRecipe(), 'id');
        $rice = $this->foodId('riz blanc cru');

        $this->call('PUT', '/api/recipes/'.$id, ['name' => 'Riz nature', 'servings' => 2, 'ingredients' => [['foodId' => $rice, 'grams' => 200]]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'Riz nature', 'servings' => 2, 'cookedWeightGrams' => null, 'servingGrams' => 100.0]);

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/recipes/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.recipe_not_found');
        $this->call('POST', '/api/nutrition/entries', ['recipeId' => $id, 'day' => $this->today, 'slot' => 'lunch', 'grams' => 100]);
        self::assertResponseStatusCodeSame(404);
        $this->call('DELETE', '/api/recipes/'.$id);
        self::assertResponseStatusCodeSame(404);

        $this->useDevice('laptop');
        $this->call('DELETE', '/api/recipes/'.$id);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->getJson('/api/recipes'));
    }

    public function testImplausibleRecipesAndEntriesAreRejected(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');

        $this->call('POST', '/api/recipes', ['name' => '', 'servings' => 0, 'cookedWeightGrams' => -1, 'ingredients' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'name'], ['propertyPath' => 'servings'], ['propertyPath' => 'cookedWeightGrams'], ['propertyPath' => 'ingredients']]]);

        $this->call('POST', '/api/recipes', ['name' => 'Fantôme', 'servings' => 1, 'ingredients' => [['foodId' => '00000000-0000-7000-8000-000000000000', 'grams' => 100]]]);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.food_not_found');

        $this->call('POST', '/api/nutrition/entries', ['foodId' => $rice, 'recipeId' => $rice, 'day' => $this->today, 'slot' => 'lunch', 'grams' => 100]);
        self::assertResponseStatusCodeSame(422);
        $this->call('POST', '/api/nutrition/entries', ['day' => $this->today, 'slot' => 'lunch', 'grams' => 100]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'foodId', 'message' => 'Indique un aliment ou une recette.']]]);
    }

    public function testAMealIsSavedAsAFavouriteAndLoggedAgainInOneGo(): void
    {
        $this->register();
        $recipe = self::string($this->createRecipe(), 'id');
        $this->logFood($this->foodId('oeuf'), 120, 'breakfast');
        $this->call('POST', '/api/nutrition/entries', ['recipeId' => $recipe, 'day' => $this->today, 'slot' => 'breakfast', 'grams' => 100]);

        $favorite = $this->call('POST', '/api/nutrition/favorite-meals', ['name' => 'Petit-déj', 'day' => $this->today, 'slot' => 'breakfast'])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Petit-déj', self::string($favorite, 'name'));
        self::assertSame(['Oeuf, cru', 'Riz au poulet'], array_column(self::array($favorite, 'items'), 'name'));
        self::assertSame([false, true], array_column(self::array($favorite, 'items'), 'recipe'));
        $id = self::string($favorite, 'id');

        $tomorrow = new \DateTimeImmutable($this->today)->modify('+1 day')->format('Y-m-d');
        $day = $this->call('POST', '/api/nutrition/favorite-meals/'.$id.'/log', ['day' => $tomorrow, 'slot' => 'gainer'])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame($tomorrow, self::string($day, 'day'));
        self::assertCount(2, self::array($day, 'meals.3.entries'));
        self::assertEqualsWithDelta(self::number($favorite, 'totals.energyKcal'), self::number($day, 'totals.energyKcal'), 0.2);

        self::assertCount(1, $this->getJson('/api/nutrition/favorite-meals'));
        $this->call('DELETE', '/api/nutrition/favorite-meals/'.$id);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->getJson('/api/nutrition/favorite-meals'));
    }

    public function testFavouritesArePrivateAndNeedAMealToKeep(): void
    {
        $this->register();
        $this->call('POST', '/api/nutrition/favorite-meals', ['name' => 'Vide', 'day' => $this->today, 'slot' => 'dinner']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_favorite_meal');

        $this->logFood($this->foodId('oeuf'), 60, 'dinner');
        $id = self::string($this->call('POST', '/api/nutrition/favorite-meals', ['name' => 'Soir', 'day' => $this->today, 'slot' => 'dinner'])->toArray(), 'id');

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->getJson('/api/nutrition/favorite-meals'));
        $this->call('POST', '/api/nutrition/favorite-meals/'.$id.'/log', ['day' => $this->today, 'slot' => 'dinner']);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.favorite_meal_not_found');
        $this->call('DELETE', '/api/nutrition/favorite-meals/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<mixed>
     */
    private function createRecipe(): array
    {
        $response = $this->call('POST', '/api/recipes', [
            'name' => 'Riz au poulet',
            'servings' => 4,
            'cookedWeightGrams' => 1100,
            'ingredients' => [
                ['foodId' => $this->foodId('riz blanc cru'), 'grams' => 300],
                ['foodId' => $this->foodId('poulet filet cru'), 'grams' => 400],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        return $response->toArray();
    }
}
