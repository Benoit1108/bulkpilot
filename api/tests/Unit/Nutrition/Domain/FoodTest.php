<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InvalidFood;
use App\Nutrition\Domain\Exception\InvalidNutritionFacts;
use App\Nutrition\Domain\Exception\InvalidPortion;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;
use App\Nutrition\Domain\Food\SearchKey;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FoodTest extends TestCase
{
    private const string FOOD = '00000000-0000-7000-8000-00000000f00d';

    private UserId $owner;
    private UserId $other;

    protected function setUp(): void
    {
        $this->owner = new UserId('00000000-0000-7000-8000-000000000001');
        $this->other = new UserId('00000000-0000-7000-8000-000000000002');
    }

    public function testFactsScaleToTheQuantityEaten(): void
    {
        $facts = new NutritionFacts(356, 7.4, 78.6, 0.7, 1.4);

        $amounts = $facts->forQuantity(150);

        self::assertEqualsWithDelta(534.0, $amounts->energyKcal, 0.001);
        self::assertEqualsWithDelta(11.1, $amounts->protein, 0.001);
        self::assertEqualsWithDelta(117.9, $amounts->carbohydrate, 0.001);
        self::assertEqualsWithDelta(1.05, $amounts->fat, 0.001);
        self::assertEqualsWithDelta(2.1, (float) $amounts->fibre, 0.001);
    }

    public function testAmountsAddUpAndKeepAnUnknownFibreUnknown(): void
    {
        $total = NutrientAmounts::sum([new NutrientAmounts(100, 10, 5, 2), new NutrientAmounts(50, 1, 2, 3)]);
        self::assertSame(150.0, $total->energyKcal);
        self::assertNull($total->fibre, 'nothing eaten says nothing about fibre');
        self::assertNull(NutrientAmounts::zero()->fibre);

        $unknown = new NutrientAmounts(100, 10, 5, 2)->add(new NutrientAmounts(50, 1, 2, 3));
        self::assertNull($unknown->fibre);
        self::assertSame(4.0, new NutrientAmounts(1, 1, 1, 1, 4)->add(new NutrientAmounts(1, 1, 1, 1))->fibre);
    }

    /**
     * @return iterable<string, array{float, float, float, float, ?float, ?float}>
     */
    public static function impossibleFacts(): iterable
    {
        yield 'negative energy' => [-1, 0, 0, 0, null, null];
        yield 'energy above pure fat' => [951, 0, 0, 100, null, null];
        yield 'more than 100 g of protein' => [400, 101, 0, 0, null, null];
        yield 'macros above 105 g' => [500, 40, 40, 30, null, null];
        yield 'sugars above carbohydrate' => [300, 0, 10, 0, 12, null];
        yield 'saturated fat above fat' => [300, 0, 0, 10, null, 11];
    }

    #[DataProvider('impossibleFacts')]
    public function testImpossibleFactsAreRejected(float $kcal, float $protein, float $carbohydrate, float $fat, ?float $sugars, ?float $saturated): void
    {
        $this->expectException(InvalidNutritionFacts::class);

        new NutritionFacts($kcal, $protein, $carbohydrate, $fat, null, $sugars, $saturated);
    }

    public function testRoundingInPublishedTablesIsTolerated(): void
    {
        $oil = new NutritionFacts(901, 0, 0, 100, null, null, 11.2);
        self::assertSame(901.0, $oil->energyKcal);
        self::assertSame(100.0, $oil->reference()->fat);
    }

    /**
     * @return iterable<string, array{string, FoodState}>
     */
    public static function names(): iterable
    {
        yield 'raw' => ['Poulet, filet, sans peau, cru', FoodState::Raw];
        yield 'cooked' => ['Riz blanc, cuit, non salé', FoodState::Cooked];
        yield 'pan-fried' => ['Poulet, filet, sans peau, sauté/poêlé', FoodState::Cooked];
        yield 'boiled egg' => ['Oeuf, dur', FoodState::Cooked];
        yield 'steamed' => ['Brocoli, cuit à la vapeur', FoodState::Cooked];
        yield 'cooked wins over "cru" in another word' => ['Jambon cru, grillé', FoodState::Cooked];
        yield 'neither' => ['Lait demi-écrémé, UHT', FoodState::NotApplicable];
        yield 'no false positive inside a word' => ['Crumble aux pommes', FoodState::NotApplicable];
        yield 'durum wheat is not hard-boiled' => ['Semoule de blé dur, crue', FoodState::Raw];
        yield 'durum wheat, cooked' => ['Pâtes de blé dur, cuites', FoodState::Cooked];
        yield 'hard-boiled eggs' => ['Oeufs durs', FoodState::Cooked];
    }

    #[DataProvider('names')]
    public function testTheStateIsReadFromTheCatalogueName(string $name, FoodState $state): void
    {
        self::assertSame($state, FoodState::fromName($name));
    }

    public function testSearchKeysIgnoreCaseAccentsAndLigatures(): void
    {
        self::assertSame('oeuf dur', SearchKey::of('Œuf  DUR'));
        self::assertSame('pates cuites barilla', SearchKey::of('Pâtes cuites', 'Barilla'));
        self::assertSame('riz', SearchKey::of('Riz', ''));
    }

    public function testCatalogueFoodsAreSharedAndReadOnly(): void
    {
        $food = Food::fromCatalogue(new FoodId(self::FOOD), FoodSource::Ciqual, '9100', ' Riz blanc, cru ', 'Céréales', new NutritionFacts(356, 7.4, 78.6, 0.7));

        self::assertSame('Riz blanc, cru', $food->name());
        self::assertSame(FoodState::Raw, $food->state());
        self::assertTrue($food->isVisibleTo($this->owner));
        self::assertFalse($food->isEditableBy($this->owner));
        self::assertSame('9100', $food->sourceReference());

        $this->expectException(InvalidFood::class);
        $food->edit('Riz', null, FoodState::Raw, new NutritionFacts(1, 0, 0, 0), []);
    }

    public function testACatalogueRefreshUpdatesValuesAndName(): void
    {
        $food = Food::fromCatalogue(new FoodId(self::FOOD), FoodSource::Ciqual, '9104', 'Riz blanc, cru', null, new NutritionFacts(356, 7.4, 78.6, 0.7));

        $food->refreshFromCatalogue('Riz blanc, cuit', 'Céréales cuites', new NutritionFacts(146, 2.9, 31.6, 0.5));

        self::assertSame(FoodState::Cooked, $food->state());
        self::assertSame(146.0, $food->facts()->energyKcal);
        self::assertSame('Céréales cuites', $food->category());
    }

    public function testCustomFoodsBelongToTheirOwner(): void
    {
        $food = $this->customFood();

        self::assertSame(FoodSource::Custom, $food->source());
        self::assertSame('Barilla', $food->brand());
        self::assertSame([['1 assiette', 250.0]], array_map(static fn (Portion $portion): array => [$portion->label, $portion->grams], $food->portions()));
        self::assertTrue($food->isEditableBy($this->owner));
        self::assertFalse($food->isVisibleTo($this->other));
        self::assertFalse($food->isEditableBy($this->other));
    }

    public function testCustomFoodsAreEditedAndSoftDeleted(): void
    {
        $food = $this->customFood();

        $food->edit('Pâtes complètes', '  ', FoodState::Cooked, new NutritionFacts(150, 5.5, 29, 1.1), []);
        self::assertSame('Pâtes complètes', $food->name());
        self::assertNull($food->brand(), 'a blank brand is no brand');
        self::assertSame([], $food->portions());

        $food->delete(new \DateTimeImmutable('2026-09-28'));
        self::assertFalse($food->isVisibleTo($this->owner));
    }

    public function testCustomFoodsAreNotRefreshedFromACatalogue(): void
    {
        $this->expectException(InvalidFood::class);

        $this->customFood()->refreshFromCatalogue('Riz', null, new NutritionFacts(1, 0, 0, 0));
    }

    public function testCatalogueFoodsCannotBeDeleted(): void
    {
        $food = Food::fromCatalogue(new FoodId(self::FOOD), FoodSource::Ciqual, '9100', 'Riz', null, new NutritionFacts(356, 7.4, 78.6, 0.7));

        $this->expectException(InvalidFood::class);
        $food->delete(new \DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string, ?string, int}>
     */
    public static function invalidCustomFoods(): iterable
    {
        yield 'blank name' => ['  ', null, 0];
        yield 'long name' => [str_repeat('a', 201), null, 0];
        yield 'long brand' => ['Pâtes', str_repeat('b', 101), 0];
        yield 'too many portions' => ['Pâtes', null, Food::MAX_PORTIONS + 1];
    }

    #[DataProvider('invalidCustomFoods')]
    public function testInvalidCustomFoodsAreRejected(string $name, ?string $brand, int $portions): void
    {
        $this->expectException(InvalidFood::class);

        Food::createCustom(new FoodId(self::FOOD), $this->owner, $name, $brand, FoodState::Cooked, new NutritionFacts(150, 5, 30, 1), array_fill(0, $portions, new Portion('1 bol', 200)));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function invalidPortions(): iterable
    {
        yield 'blank label' => [' ', 100];
        yield 'long label' => [str_repeat('x', 41), 100];
        yield 'no weight' => ['1 bol', 0];
        yield 'too heavy' => ['1 marmite', 5001];
    }

    #[DataProvider('invalidPortions')]
    public function testInvalidPortionsAreRejected(string $label, float $grams): void
    {
        $this->expectException(InvalidPortion::class);

        new Portion($label, $grams);
    }

    private function customFood(): Food
    {
        return Food::createCustom(new FoodId(self::FOOD), $this->owner, 'Pâtes cuites', ' Barilla ', FoodState::Cooked, new NutritionFacts(157, 5.8, 30.6, 0.9), [new Portion(' 1 assiette ', 250)]);
    }
}
