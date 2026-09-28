<?php

declare(strict_types=1);

namespace App\Tests\Integration\Nutrition;

use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeComposition;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecipePersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserId $alex;
    private UserId $sam;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->sam = new UserId('00000000-0000-7000-8000-000000000511');
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testRecipesAndFavouritesRoundTripAndStayPrivate(): void
    {
        $foods = self::getContainer()->get(FoodRepository::class);
        $rice = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000f1'), FoodSource::Ciqual, '9100', 'Riz blanc, cru', null, new NutritionFacts(350, 7, 78, 1));
        $skyr = Food::createCustom(new FoodId('00000000-0000-7000-8000-0000000000f2'), $this->alex, 'Skyr', null, FoodState::NotApplicable, new NutritionFacts(63, 11, 4, 0.2), []);
        $foods->save($rice);
        $foods->save($skyr);
        $recipes = self::getContainer()->get(RecipeRepository::class);
        $recipe = Recipe::create(new RecipeId('00000000-0000-7000-8000-00000000000a'), $this->alex, 'Bol', 2, 600, [new Ingredient($rice->id(), 150), new Ingredient($skyr->id(), 300)], $this->now);
        $recipes->save($recipe);
        $favorites = self::getContainer()->get(FavoriteMealRepository::class);
        $favorites->save(FavoriteMeal::create(new FavoriteMealId('00000000-0000-7000-8000-0000000000fa'), $this->alex, 'Matin', [new FavoriteItem($rice->id(), null, 80), new FavoriteItem(null, $recipe->id(), 300)], $this->now));
        $entries = self::getContainer()->get(JournalEntryRepository::class);
        $entries->save(JournalEntry::log(new JournalEntryId('00000000-0000-7000-8000-0000000000e1'), $this->alex, $this->now, MealSlot::Lunch, LoggedFood::snapshotOfRecipe($recipe, RecipeComposition::of($recipe, [$rice->id()->value => $rice, $skyr->id()->value => $skyr])), 300, $this->now));
        $skyr->delete($this->now);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $recipes->getVisibleTo($recipe->id(), $this->alex);
        self::assertSame([['00000000-0000-7000-8000-0000000000f1', 150.0], ['00000000-0000-7000-8000-0000000000f2', 300.0]], array_map(static fn (Ingredient $i): array => [$i->foodId->value, $i->grams], $loaded->ingredients()));
        self::assertSame(600.0, $loaded->cookedWeightGrams());
        self::assertCount(1, $recipes->recipesOf($this->alex));
        $recipes->save(Recipe::create(new RecipeId('00000000-0000-7000-8000-00000000000b'), $this->alex, 'avoine', 1, null, [new Ingredient($rice->id(), 50)], $this->now));
        $this->entityManager->flush();
        self::assertSame(['avoine', 'Bol'], array_map(static fn (Recipe $r): string => $r->name(), $recipes->recipesOf($this->alex)), 'by name, case aside');
        self::assertSame([], $recipes->recipesOf($this->sam));

        $composing = $foods->composing($this->alex, [$rice->id(), $skyr->id()]);
        self::assertCount(2, $composing, 'the deleted custom food still composes the recipe');
        self::assertSame([], $foods->composing($this->sam, [$skyr->id()]), 'but only for its owner');

        $favorite = $favorites->getOwnedBy(new FavoriteMealId('00000000-0000-7000-8000-0000000000fa'), $this->alex);
        self::assertSame($rice->id()->value, $favorite->items()[0]->foodId?->value);
        self::assertSame($recipe->id()->value, $favorite->items()[1]->recipeId?->value);
        self::assertCount(1, $favorites->favoritesOf($this->alex));

        $entry = $entries->ofDay($this->alex, $this->now)[0];
        self::assertSame($recipe->id()->value, $entry->food()->recipeId?->value);
        self::assertNull($entry->food()->foodId);
        self::assertSame([], $entries->recentFoods($this->alex, 10), 'recipes are not recent foods');

        $loaded->delete($this->now);
        $this->entityManager->flush();
        self::assertSame(['avoine'], array_map(static fn (Recipe $r): string => $r->name(), $recipes->recipesOf($this->alex)));
        self::assertTrue($recipes->getOwnedBy($recipe->id(), $this->alex)->belongsTo($this->alex));

        $favorites->remove($favorite);
        $this->entityManager->flush();
        self::assertSame([], $favorites->favoritesOf($this->alex));
    }
}
