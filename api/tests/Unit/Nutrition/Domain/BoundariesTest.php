<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InconsistentTarget;
use App\Nutrition\Domain\Exception\InvalidBarcode;
use App\Nutrition\Domain\Exception\InvalidEnergyInputs;
use App\Nutrition\Domain\Exception\InvalidFavoriteMeal;
use App\Nutrition\Domain\Exception\InvalidFood;
use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Nutrition\Domain\Exception\InvalidMealSplit;
use App\Nutrition\Domain\Exception\InvalidNutritionFacts;
use App\Nutrition\Domain\Exception\InvalidPortion;
use App\Nutrition\Domain\Exception\InvalidRecipe;
use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;
use App\Nutrition\Domain\Food\SearchKey;
use App\Nutrition\Domain\Journal\JournalDays;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\Sex;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The exact edges of Nutrition's rules: a limit is accepted, one beyond it is not, and lengths
 * count characters, not bytes.
 */
final class BoundariesTest extends TestCase
{
    private const string FOOD = '00000000-0000-7000-8000-000000000001';
    private const string OWNER = '0199a0b1-0000-7000-8000-000000000001';

    public function testNamesCountCharactersUpToTheirLimit(): void
    {
        $owner = new UserId(self::OWNER);
        $facts = new NutritionFacts(100, 10, 10, 1);
        $food = static fn (string $name, ?string $brand = null): Food => Food::createCustom(new FoodId(self::FOOD), $owner, $name, $brand, FoodState::NotApplicable, $facts, []);

        self::assertSame(Food::MAX_NAME_LENGTH, mb_strlen($food(str_repeat('é', Food::MAX_NAME_LENGTH))->name()));
        self::assertSame(Food::MAX_BRAND_LENGTH, mb_strlen((string) $food('Riz', str_repeat('é', Food::MAX_BRAND_LENGTH))->brand()));
        self::assertSame(Portion::MAX_LABEL_LENGTH, mb_strlen(new Portion(str_repeat('é', Portion::MAX_LABEL_LENGTH), 10)->label));
        self::assertSame(Recipe::MAX_NAME_LENGTH, mb_strlen(self::recipe(str_repeat('é', Recipe::MAX_NAME_LENGTH))->name()));
        self::assertSame(100, mb_strlen(FavoriteMeal::create(new FavoriteMealId(self::FOOD), $owner, str_repeat('é', 100), [new FavoriteItem(new FoodId(self::FOOD), null, 100)], new \DateTimeImmutable())->name()));

        $this->expectException(InvalidFood::class);
        $food(str_repeat('a', Food::MAX_NAME_LENGTH + 1));
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, class-string<\Throwable>}>
     */
    public static function oneBeyond(): iterable
    {
        $owner = new UserId(self::OWNER);
        $item = new FavoriteItem(new FoodId(self::FOOD), null, 100);

        yield 'a brand too long' => [static fn () => Food::createCustom(new FoodId(self::FOOD), $owner, 'Riz', str_repeat('a', Food::MAX_BRAND_LENGTH + 1), FoodState::Raw, new NutritionFacts(100, 1, 1, 1), []), InvalidFood::class];
        yield 'a portion label too long' => [static fn () => new Portion(str_repeat('a', Portion::MAX_LABEL_LENGTH + 1), 10), InvalidPortion::class];
        yield 'a favourite name too long' => [static fn () => FavoriteMeal::create(new FavoriteMealId(self::FOOD), $owner, str_repeat('a', 101), [$item], new \DateTimeImmutable()), InvalidFavoriteMeal::class];
        yield 'a favourite with too many items' => [static fn () => FavoriteMeal::create(new FavoriteMealId(self::FOOD), $owner, 'Petit-déjeuner', array_fill(0, FavoriteMeal::MAX_ITEMS + 1, $item), new \DateTimeImmutable()), InvalidFavoriteMeal::class];
        yield 'too many portions' => [static fn () => Food::createCustom(new FoodId(self::FOOD), $owner, 'Riz', null, FoodState::Raw, new NutritionFacts(100, 1, 1, 1), array_fill(0, Food::MAX_PORTIONS + 1, new Portion('bol', 100))), InvalidFood::class];
        yield 'a recipe name too long' => [static fn () => self::recipe(str_repeat('a', Recipe::MAX_NAME_LENGTH + 1)), InvalidRecipe::class];
        yield 'too many servings' => [static fn () => self::recipe(servings: Recipe::MAX_SERVINGS + 1), InvalidRecipe::class];
        yield 'too heavy a pot' => [static fn () => self::recipe(cooked: Recipe::MAX_COOKED_GRAMS + 1), InvalidRecipe::class];
        yield 'too many ingredients' => [static fn () => self::recipe(ingredients: Recipe::MAX_INGREDIENTS + 1), InvalidRecipe::class];
        yield 'too much of an ingredient' => [static fn () => new Ingredient(new FoodId(self::FOOD), Ingredient::MAX_GRAMS + 1), InvalidRecipe::class];
        yield 'too heavy a portion' => [static fn () => new Portion('bol', Portion::MAX_GRAMS + 1), InvalidPortion::class];
        yield 'too much in a favourite' => [static fn () => new FavoriteItem(new FoodId(self::FOOD), null, JournalEntry::MAX_GRAMS + 1), InvalidFavoriteMeal::class];
        yield 'too energetic' => [static fn () => new NutritionFacts(NutritionFacts::MAX_ENERGY_KCAL + 1, 1, 1, 1), InvalidNutritionFacts::class];
        yield 'macros beyond 105 g' => [static fn () => new NutritionFacts(500, 35, 35, 35.1), InvalidNutritionFacts::class];
        yield 'more sugars than carbohydrate' => [static fn () => new NutritionFacts(100, 1, 10, 1, sugars: 10.6), InvalidNutritionFacts::class];
        yield 'more saturated fat than fat' => [static fn () => new NutritionFacts(100, 1, 1, 10, saturatedFat: 10.6), InvalidNutritionFacts::class];
        yield 'a 9-digit code with a valid check digit' => [static fn () => new Barcode('123456784'), InvalidBarcode::class];
        yield 'a split share over 100 %' => [static fn () => MealSplit::defaultFor($owner)->change(['dinner' => 101]), InvalidMealSplit::class];
        yield 'too old an age' => [static fn () => self::inputs(age: EnergyInputs::MAX_AGE + 1), InvalidEnergyInputs::class];
    }

    /**
     * @param \Closure(): mixed        $make
     * @param class-string<\Throwable> $error
     */
    #[DataProvider('oneBeyond')]
    public function testOneBeyondTheLimitIsRefused(\Closure $make, string $error): void
    {
        $this->expectException($error);
        $make();
    }

    public function testTheLimitsThemselvesAreAccepted(): void
    {
        $owner = new UserId(self::OWNER);
        $item = new FavoriteItem(new FoodId(self::FOOD), null, JournalEntry::MAX_GRAMS);

        self::assertCount(FavoriteMeal::MAX_ITEMS, FavoriteMeal::create(new FavoriteMealId(self::FOOD), $owner, 'Repas', array_fill(0, FavoriteMeal::MAX_ITEMS, $item), new \DateTimeImmutable())->items());
        self::assertCount(Food::MAX_PORTIONS, Food::createCustom(new FoodId(self::FOOD), $owner, 'Riz', null, FoodState::Raw, new NutritionFacts(100, 1, 1, 1), array_fill(0, Food::MAX_PORTIONS, new Portion('bol', Portion::MAX_GRAMS)))->portions());
        $recipe = self::recipe(servings: Recipe::MAX_SERVINGS, cooked: Recipe::MAX_COOKED_GRAMS, ingredients: Recipe::MAX_INGREDIENTS);
        self::assertSame([Recipe::MAX_SERVINGS, Recipe::MAX_COOKED_GRAMS, Recipe::MAX_INGREDIENTS], [$recipe->servings(), $recipe->cookedWeightGrams(), \count($recipe->ingredients())]);
        self::assertSame(Ingredient::MAX_GRAMS, new Ingredient(new FoodId(self::FOOD), Ingredient::MAX_GRAMS)->grams);
        self::assertSame(NutritionFacts::MAX_ENERGY_KCAL, new NutritionFacts(NutritionFacts::MAX_ENERGY_KCAL, 0, 0, 100)->energyKcal);
        self::assertSame(35.0, new NutritionFacts(500, 35, 35, 35, sugars: 35.5, saturatedFat: 35.5)->fat, '105 g of macros, sugars and saturated fat half a gram over');
        self::assertSame(10.0, new NutritionFacts(100, 1, 10, 1, sugars: 10.0)->sugars, 'all the carbohydrate as sugars');
        self::assertSame(100, MealSplit::of($owner, ['dinner' => 100])->shareOf(MealSlot::Dinner));
    }

    public function testQuantitiesAreKeptToTheTenthOfAGram(): void
    {
        self::assertSame(123.5, self::recipe(cooked: 123.46)->cookedWeightGrams());
        self::assertSame(12.4, new Ingredient(new FoodId(self::FOOD), 12.36)->grams);
    }

    public function testAFailedFactExplainsItself(): void
    {
        try {
            new NutritionFacts(-1, 0, 0, 0);
            self::fail('negative energy');
        } catch (InvalidNutritionFacts $error) {
            self::assertStringContainsString('energy must be between 0 and 950 kcal', $error->getMessage());
        }
    }

    public function testBarcodesWithTheirEdgeCheckDigits(): void
    {
        self::assertSame('4006381333900', new Barcode('4006381333900')->value, 'a check digit of 0');
        self::assertSame('96385074', new Barcode('96385074')->value, 'an EAN-8');
        self::assertSame('96385074', new Barcode('00000096385074')->value, 'a GTIN-14 holding an EAN-8');
        try {
            new Barcode('4006381333901');
            self::fail('wrong check digit');
        } catch (InvalidBarcode $error) {
            self::assertStringContainsString('check digit', $error->getMessage());
        }
    }

    public function testThePreparationIsReadWhateverTheCase(): void
    {
        self::assertSame(FoodState::Cooked, FoodState::fromName('POULET RÔTI'));
    }

    public function testSearchWordsAreDistinctLongEnoughAndAtMostSix(): void
    {
        self::assertSame(['ri', 'blanc', 'cuit'], SearchKey::words('ri blanc a cuit blanc'));
        self::assertSame(['un', 'deux', 'trois', 'quatre', 'cinq', 'six'], SearchKey::words('un deux trois quatre cinq six sept'));
        self::assertSame(['éé', 'ab'], SearchKey::words('éé ab'), 'two letters, accented or not');
        self::assertSame(['ab'], SearchKey::words('é ab'), 'one letter, however many bytes');
        self::assertSame('boeuf mais aeble strasse', SearchKey::of('Bœuf', '', 'maïs', 'Æble', 'Straße'));
        self::assertSame('riz', SearchKey::of('Riz', ''));
    }

    public function testFibreAddsUpWhenEitherSideHasIt(): void
    {
        self::assertSame(5.0, new NutrientAmounts(1, 1, 1, 1, 2.0)->add(new NutrientAmounts(1, 1, 1, 1, 3.0))->fibre);
        self::assertSame(2.0, new NutrientAmounts(1, 1, 1, 1)->add(new NutrientAmounts(1, 1, 1, 1, 2.0))->fibre);
        self::assertSame(2.0, new NutrientAmounts(1, 1, 1, 1, 2.0)->add(new NutrientAmounts(1, 1, 1, 1))->fibre);
    }

    public function testADayExactlyAsOldAsAllowedCanBeLogged(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 00:30');
        $oldest = new \DateTimeImmutable('2026-09-30')->modify(\sprintf('-%d days', JournalDays::MAX_PAST_DAYS));

        self::assertSame($oldest->format('Y-m-d'), new JournalDays()->assertLoggable($oldest, $now)->format('Y-m-d'));
        $this->expectException(InvalidJournalDay::class);
        new JournalDays()->assertLoggable($oldest->modify('-1 day'), $now);
    }

    public function testAMissingSlotIsZeroAndAnUnknownOneIsRefused(): void
    {
        $split = MealSplit::of(new UserId(self::OWNER), ['breakfast' => 100]);
        self::assertSame(0, $split->shareOf(MealSlot::Lunch));

        try {
            $split->change(['breakfast' => 50, 'brunch' => 50]);
            self::fail('unknown slot');
        } catch (InvalidMealSplit $error) {
            self::assertStringContainsString('unknown meal slot', $error->getMessage());
        }
    }

    /**
     * @return iterable<string, array{EnergyInputs, array{int, int, int, int}}>
     */
    public static function estimates(): iterable
    {
        yield 'a woman bulking' => [self::inputs(Sex::Female, 25, 165, 52.3, Lifestyle::Light, 3, Goal::Bulk, 0.3), [2230, 94, 324, 62]];
        yield 'a heavy man cutting: protein capped to what fits' => [self::inputs(Sex::Male, 40, 175, 140.7, Lifestyle::Sedentary, 0, Goal::Cut, -1.0), [1670, 228, 1, 84]];
        yield 'the safety floor' => [self::inputs(Sex::Female, 60, 150, 45.5, Lifestyle::Sedentary, 0, Goal::Cut, -0.5), [1200, 100, 126, 33]];
        yield 'a man maintaining' => [self::inputs(Sex::Male, 33, 181, 77.7, Lifestyle::Active, 4, Goal::Maintain, 0.0), [2970, 124, 432, 83]];
        yield 'fat capped to half the calories' => [self::inputs(Sex::Female, 13, 100, 300.0, Lifestyle::Sedentary, 0, Goal::Cut, -1.0), [2980, 373, 1, 165]];
        yield 'a light cut' => [self::inputs(Sex::Male, 50, 170, 63.3, Lifestyle::Light, 2, Goal::Cut, -0.3), [1770, 139, 193, 49]];
        yield 'a man bulking' => [self::inputs(Sex::Male, 22, 190, 66.1, Lifestyle::Light, 5, Goal::Bulk, 0.5), [3340, 119, 507, 93]];
    }

    /**
     * @param array{int, int, int, int} $expected
     */
    #[DataProvider('estimates')]
    public function testTheEstimateSplitsTheMacrosExactly(EnergyInputs $inputs, array $expected): void
    {
        $targets = new EnergyCalculator()->estimate($inputs)->targets;

        self::assertSame($expected, [$targets->energyKcal, $targets->proteinGrams, $targets->carbohydrateGrams, $targets->fatGrams]);
    }

    public function testTheInputBoundsAreInclusive(): void
    {
        self::assertSame([EnergyInputs::MIN_AGE, EnergyInputs::MAX_AGE], [self::inputs(age: EnergyInputs::MIN_AGE)->age, self::inputs(age: EnergyInputs::MAX_AGE)->age]);
        self::assertSame(EnergyInputs::MIN_HEIGHT_CM, self::inputs(height: EnergyInputs::MIN_HEIGHT_CM)->heightCm);
        self::assertSame(EnergyInputs::MIN_WEIGHT_KG, self::inputs(weight: EnergyInputs::MIN_WEIGHT_KG)->weightKg);
        self::assertSame(EnergyInputs::MIN_WEEKLY_CHANGE_KG, self::inputs(goal: Goal::Bulk, change: EnergyInputs::MIN_WEEKLY_CHANGE_KG)->weeklyWeightChangeKg);
        self::assertSame(-EnergyInputs::MIN_WEEKLY_CHANGE_KG, self::inputs(goal: Goal::Cut, change: -EnergyInputs::MIN_WEEKLY_CHANGE_KG)->weeklyWeightChangeKg);
        self::assertSame(-EnergyInputs::MAX_WEEKLY_CHANGE_KG, self::inputs(goal: Goal::Cut, change: -EnergyInputs::MAX_WEEKLY_CHANGE_KG)->weeklyWeightChangeKg);
    }

    public function testTargetsAtTheirEdges(): void
    {
        self::assertSame(250, new MacroTargets(MacroTargets::MIN_ENERGY_KCAL, 0, 250, 0)->carbohydrateGrams, 'the lowest target, no protein nor fat');
        self::assertSame(2000, new MacroTargets(2000, 100, 299, 56)->energyKcal, 'macros exactly 5 % over (2 100 kcal)');
        self::assertFalse(new MacroTargets(2000, 100, 300, 50)->equals(new MacroTargets(2000, 110, 290, 50)));
        self::assertFalse(new MacroTargets(2000, 100, 300, 50)->equals(new MacroTargets(2010, 100, 300, 50)), 'other calories, same macros');
        self::assertSame(288, new MacroTargets(2000, 100, 287, 50)->withEnergy(2003)->carbohydrateGrams, '288.25 g rounded');

        foreach ([[2000, -1, 400, 60], [2000, 100, -1, 180], [2000, 100, 300, -1]] as [$kcal, $protein, $carbohydrate, $fat]) {
            try {
                new MacroTargets($kcal, $protein, $carbohydrate, $fat);
                self::fail('negative macro');
            } catch (InconsistentTarget $error) {
                self::assertStringContainsString('macros cannot be negative', $error->getMessage());
            }
        }
        try {
            new MacroTargets(2000, 100, 287, 50)->withEnergy(800);
            self::fail('below the protein and fat');
        } catch (InconsistentTarget $error) {
            self::assertStringContainsString('the protein and fat alone give more than 800 kcal', $error->getMessage());
        }
    }

    private static function recipe(string $name = 'Chili', int $servings = 4, ?float $cooked = null, int $ingredients = 1): Recipe
    {
        return Recipe::create(new RecipeId(self::FOOD), new UserId(self::OWNER), $name, $servings, $cooked, array_fill(0, $ingredients, new Ingredient(new FoodId(self::FOOD), 100)), new \DateTimeImmutable());
    }

    private static function inputs(
        Sex $sex = Sex::Male,
        int $age = 30,
        int $height = 180,
        float $weight = 75.0,
        Lifestyle $lifestyle = Lifestyle::Light,
        int $sessions = 3,
        Goal $goal = Goal::Maintain,
        float $change = 0.0,
    ): EnergyInputs {
        return new EnergyInputs($sex, $age, $height, $weight, $lifestyle, $sessions, $goal, $change);
    }
}
