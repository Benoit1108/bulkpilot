<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Catalogue\CatalogueEntry;
use App\Nutrition\Application\Catalogue\ImportCatalogue;
use App\Nutrition\Application\Catalogue\ImportCatalogueHandler;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\CreateCustomFoodHandler;
use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Application\Food\DeleteCustomFood;
use App\Nutrition\Application\Food\DeleteCustomFoodHandler;
use App\Nutrition\Application\Food\EditCustomFood;
use App\Nutrition\Application\Food\EditCustomFoodHandler;
use App\Nutrition\Application\Food\GetFood;
use App\Nutrition\Application\Food\GetFoodHandler;
use App\Nutrition\Application\Food\ListCustomFoods;
use App\Nutrition\Application\Food\ListCustomFoodsHandler;
use App\Nutrition\Application\Food\ListRecentFoods;
use App\Nutrition\Application\Food\ListRecentFoodsHandler;
use App\Nutrition\Application\Food\SearchFoods;
use App\Nutrition\Application\Food\SearchFoodsHandler;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Port\FoodCatalogue;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class FoodsTest extends NutritionTestCase
{
    private const string PASTA = '00000000-0000-7000-8000-0000000000aa';

    public function testSearchNeedsTwoCharactersAndMatchesEveryWord(): void
    {
        $this->rice();
        $handler = new SearchFoodsHandler($this->foods);

        self::assertSame([], $handler(new SearchFoods($this->alex, ' r ')));
        $found = $handler(new SearchFoods($this->alex, 'RIZ cru'));
        self::assertCount(1, $found);
        self::assertSame('Riz blanc, cru', $found[0]->name);
        self::assertFalse($found[0]->editable);
        self::assertSame([], $handler(new SearchFoods($this->alex, 'riz cuit')));
    }

    public function testSearchResultsAreCapped(): void
    {
        for ($i = 0; $i < SearchFoods::MAX_RESULTS + 5; ++$i) {
            $this->catalogueFood((string) $i, 'Riz '.$i, $this->rice()->facts());
        }

        self::assertCount(SearchFoods::MAX_RESULTS, new SearchFoodsHandler($this->foods)(new SearchFoods($this->alex, 'riz', 500)));
        self::assertCount(1, new SearchFoodsHandler($this->foods)(new SearchFoods($this->alex, 'riz', 0)));
    }

    public function testCustomFoodsAreCreatedEditedAndDeletedByTheirOwnerOnly(): void
    {
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::PASTA), $this->alex, $this->pasta()));

        $view = new GetFoodHandler($this->foods)(new GetFood($this->alex, new FoodId(self::PASTA)));
        self::assertSame(FoodSource::Custom, $view->source);
        self::assertTrue($view->editable);
        self::assertSame('1 assiette', $view->portions[0]->label);
        self::assertSame(30.6, $view->per100Grams->carbohydrate);
        self::assertCount(1, new ListCustomFoodsHandler($this->foods)(new ListCustomFoods($this->alex)));
        self::assertSame([], new ListCustomFoodsHandler($this->foods)(new ListCustomFoods($this->sam)));

        new EditCustomFoodHandler($this->foods)(new EditCustomFood(new FoodId(self::PASTA), $this->alex, new CustomFoodDetails('Pâtes complètes', null, FoodState::Cooked, 150, 5.5, 29, 1.1)));
        self::assertSame('Pâtes complètes', new GetFoodHandler($this->foods)(new GetFood($this->alex, new FoodId(self::PASTA)))->name);

        try {
            new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::PASTA), $this->sam));
            self::fail('someone else\'s food is not found');
        } catch (FoodNotFound) {
        }

        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::PASTA), $this->alex));
        $this->expectException(FoodNotFound::class);
        new GetFoodHandler($this->foods)(new GetFood($this->alex, new FoodId(self::PASTA)));
    }

    public function testRecentFoodsAreTheLastLoggedOnesStillVisible(): void
    {
        $rice = $this->rice();
        $oats = $this->catalogueFood('9310', 'Flocons d\'avoine', $rice->facts());
        new CreateCustomFoodHandler($this->foods)(new CreateCustomFood(new FoodId(self::PASTA), $this->alex, $this->pasta()));
        $log = new LogFoodHandler($this->foods, $this->writer());
        foreach ([$rice->id(), new FoodId(self::PASTA), $oats->id(), $rice->id()] as $i => $foodId) {
            $this->clock->modify('+1 minute');
            $log(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $foodId, $this->day(), MealSlot::Lunch, 100 + $i));
        }
        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId(self::PASTA), $this->alex));

        $recent = new ListRecentFoodsHandler($this->entries, $this->foods)(new ListRecentFoods($this->alex));

        self::assertSame(['Riz blanc, cru', 'Flocons d\'avoine'], array_column($recent, 'name'));
        self::assertSame([], new ListRecentFoodsHandler($this->entries, $this->foods)(new ListRecentFoods($this->sam)));
    }

    public function testTheCatalogueImportIsIdempotentAndSkipsUnusableFoods(): void
    {
        $catalogue = new class implements FoodCatalogue {
            public float $riceKcal = 356;

            public function source(): FoodSource
            {
                return FoodSource::Ciqual;
            }

            public function read(?string $location): iterable
            {
                yield new CatalogueEntry('9100', 'Riz blanc, cru', 'Céréales', $this->riceKcal, 7.4, 78.6, 0.7, 1.4, 0.3, 0.2, 0.01);
                yield new CatalogueEntry('17440', 'Huile de tournesol', 'Huiles', 901, null, null, 100, null, null, 11, null);
                // No energy, but every macro: computed with the EU factors (4·20 + 4·10 + 9·5 + 2·4 = 173).
                yield new CatalogueEntry('1', 'Mélange', null, null, 20, 10, 5, 4, 12, 4.5, null);
                yield new CatalogueEntry('2', 'Sans énergie', null, null, 20, null, 5, null, null, null, null);
                yield new CatalogueEntry('3', 'Sans macros', null, 50, null, null, null, null, null, null, null);
                yield new CatalogueEntry('4', 'Aberrant', null, 2000, 10, 10, 10, null, null, null, null);
            }
        };
        $handler = new ImportCatalogueHandler($catalogue, $this->foods, $this->uuids);

        $first = new ImportCatalogue('/tmp/ciqual');
        $handler($first);
        self::assertSame(3, $first->report->created);
        self::assertSame(['2', '3', '4'], array_map('strval', array_keys($first->report->skipped)));

        $blend = $this->catalogued('1');
        self::assertSame(173.0, $blend->facts()->energyKcal);
        self::assertNull($blend->facts()->sugars, 'sugars above carbohydrate are dropped, not the food');
        self::assertSame(4.5, $blend->facts()->saturatedFat);
        self::assertSame(0.0, $this->catalogued('17440')->facts()->protein);

        $catalogue->riceKcal = 350;
        $second = new ImportCatalogue();
        $handler($second);
        self::assertSame(0, $second->report->created);
        self::assertSame(3, $second->report->updated);
        self::assertCount(3, $this->foods->foods);
        self::assertSame(350.0, $this->catalogued('9100')->facts()->energyKcal);
    }

    private function catalogued(string $reference): Food
    {
        $food = $this->foods->catalogue(FoodSource::Ciqual)[$reference] ?? null;
        self::assertNotNull($food, $reference);

        return $food;
    }

    private function pasta(): CustomFoodDetails
    {
        return new CustomFoodDetails('Pâtes cuites', 'Barilla', FoodState::Cooked, 157, 5.8, 30.6, 0.9, 1.8, 0.5, 0.2, 0.01, [['label' => '1 assiette', 'grams' => 250.0]]);
    }
}
