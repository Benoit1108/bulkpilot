<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\GetScannedProduct;
use App\Nutrition\Application\Barcode\GetScannedProductHandler;
use App\Nutrition\Application\Barcode\IncompleteProduct;
use App\Nutrition\Application\Barcode\KeepScannedProduct;
use App\Nutrition\Application\Barcode\KeepScannedProductHandler;
use App\Nutrition\Application\Barcode\ScanBarcode;
use App\Nutrition\Application\Barcode\ScanBarcodeHandler;
use App\Nutrition\Application\Barcode\ScanView;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\CreateCustomFoodHandler;
use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Application\Food\DeleteCustomFood;
use App\Nutrition\Application\Food\DeleteCustomFoodHandler;
use App\Nutrition\Application\Food\EditCustomFood;
use App\Nutrition\Application\Food\EditCustomFoodHandler;
use App\Nutrition\Application\Food\FindOwnFoodByBarcode;
use App\Nutrition\Application\Food\FindOwnFoodByBarcodeHandler;
use App\Nutrition\Application\Food\OwnBarcodes;
use App\Nutrition\Domain\Exception\BarcodeAlreadyUsed;
use App\Nutrition\Domain\Exception\ProductNotFound;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Nutrition\NutritionTestCase;
use App\Tests\Support\Nutrition\StubFoodDatabaseProvider;

final class BarcodeTest extends NutritionTestCase
{
    private const string SPREAD = '3017620422003';
    private const string SODA = '5449000000996';

    private StubFoodDatabaseProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new StubFoodDatabaseProvider();
        $this->provider->products[self::SPREAD] = new ExternalProduct(new Barcode(self::SPREAD), 'Pâte à tartiner', 'Nutella', 'Pâtes à tartiner', 539, 6.3, 57.5, 30.9, servingGrams: 15, quantity: '400 g');
    }

    public function testAnUnknownCodeIsProposedFromTheDatabaseAndNothingIsSaved(): void
    {
        $scan = $this->scan(self::SPREAD);

        self::assertNull($scan->food);
        self::assertNotNull($scan->proposal);
        self::assertTrue($scan->proposal->isComplete());
        self::assertSame('Nutella', $scan->proposal->product->brand);
        self::assertSame([], $this->foods->foods, 'a scan saves nothing (spec §20)');
    }

    public function testAProductConfirmedAsIsBecomesSharedAndIsKnownToEveryone(): void
    {
        $this->confirm(self::SPREAD);

        $food = $this->foods->findByBarcode($this->sam, new Barcode(self::SPREAD));
        self::assertNotNull($food);
        self::assertSame(FoodSource::OpenFoodFacts, $food->source());
        self::assertSame('1 portion', $food->portions()[0]->label);
        self::assertSame(15.0, $food->portions()[0]->grams);

        $calls = $this->provider->calls;
        $scan = $this->scan(self::SPREAD, $this->sam);
        self::assertSame($food->id()->value, $scan->food?->id);
        self::assertSame($calls, $this->provider->calls, 'a known product is not asked again');
    }

    public function testConfirmingAgainRefreshesTheSharedProduct(): void
    {
        $this->confirm(self::SPREAD);
        $this->provider->products[self::SPREAD] = new ExternalProduct(new Barcode(self::SPREAD), 'Pâte à tartiner, recette 2026', 'Nutella', null, 530, 6, 57, 30);

        $this->confirm(self::SPREAD, '00000000-0000-7000-8000-0000000000f2');

        self::assertCount(1, $this->foods->foods);
        self::assertSame(530.0, $this->foods->findByBarcode($this->alex, new Barcode(self::SPREAD))?->facts()->energyKcal);
    }

    public function testAnIncompleteProductMustBeCompleted(): void
    {
        $this->provider->products[self::SODA] = new ExternalProduct(new Barcode(self::SODA), 'Soda', null, null, 42, null, 10.6, null);

        $proposal = $this->scan(self::SODA)->proposal;
        self::assertSame(['protein', 'fat'], $proposal?->missing);
        self::assertFalse($proposal->isComplete());

        $this->expectException(IncompleteProduct::class);
        $this->confirm(self::SODA);
    }

    public function testImplausibleValuesAreFlaggedWithTheSuspectValue(): void
    {
        $this->provider->products[self::SODA] = new ExternalProduct(new Barcode(self::SODA), 'Mal saisi', null, null, 400, 60, 60, 20);

        $proposal = $this->scan(self::SODA)->proposal;

        self::assertSame([], $proposal?->missing);
        self::assertSame('macros', $proposal->suspect);
        self::assertFalse($proposal->isComplete());
    }

    public function testAProductWithoutNameOrWithAnOddServingIsHandled(): void
    {
        $this->provider->products[self::SODA] = new ExternalProduct(new Barcode(self::SODA), '  ', null, null, 42, 0, 10.6, 0, servingGrams: 99999);

        $proposal = $this->scan(self::SODA)->proposal;

        self::assertSame(['name'], $proposal?->missing);
        self::assertSame([], $proposal->portions(), 'a serving outside plausible weights is dropped');
    }

    public function testACodeNobodyKnowsIsNotFound(): void
    {
        $this->expectException(ProductNotFound::class);

        $this->scan(self::SODA);
    }

    public function testConfirmingANoLongerKnownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        $this->confirm(self::SODA);
    }

    public function testCorrectedValuesMakeTheUsersOwnFoodWhichTheyScanFirst(): void
    {
        $this->confirm(self::SPREAD);
        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f2', $this->corrected('Ma pâte', 500));

        $mine = $this->scan(self::SPREAD)->food;
        self::assertSame('Ma pâte', $mine?->name);
        self::assertTrue($mine->editable);
        self::assertSame(FoodSource::OpenFoodFacts, $this->scan(self::SPREAD, $this->sam)->food?->source, 'others still get the shared one');

        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f3', $this->corrected('Ma pâte allégée', 450));
        self::assertSame('Ma pâte allégée', $this->scan(self::SPREAD)->food?->name);
        self::assertCount(2, $this->foods->foods, 'one own food per barcode');
    }

    public function testAProductUnknownEverywhereCanStillBeKeptWithTheUsersValues(): void
    {
        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f1', $this->corrected('Soda maison', 40, self::SODA));

        self::assertSame('Soda maison', $this->scan(self::SODA)->food?->name);
        self::assertSame(0, $this->provider->calls, 'corrections do not need the database, nor a scan of one\'s own food');
    }

    public function testTheConfirmedProductIsReadBackFromTheSharedCatalogue(): void
    {
        $this->confirm(self::SPREAD);
        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f2', $this->corrected('Ma pâte', 500));

        $shared = new GetScannedProductHandler($this->foods)(new GetScannedProduct($this->alex, new Barcode(self::SPREAD)));
        self::assertSame(FoodSource::OpenFoodFacts, $shared->source, 'the shared product, even when the user has their own');
        self::assertFalse($shared->editable);

        $this->expectException(ProductNotFound::class);
        new GetScannedProductHandler($this->foods)(new GetScannedProduct($this->alex, new Barcode(self::SODA)));
    }

    public function testADeletedOwnFoodFreesItsBarcode(): void
    {
        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f1', $this->corrected('Ma pâte', 500));
        new DeleteCustomFoodHandler($this->foods, $this->clock)(new DeleteCustomFood(new FoodId('00000000-0000-7000-8000-0000000000f1'), $this->alex));

        self::assertNull(new FindOwnFoodByBarcodeHandler($this->foods)(new FindOwnFoodByBarcode($this->alex, new Barcode(self::SPREAD))));
        $this->correct($this->alex, '00000000-0000-7000-8000-0000000000f2', $this->corrected('Ma nouvelle pâte', 480));
        self::assertSame('00000000-0000-7000-8000-0000000000f2', $this->scan(self::SPREAD)->food?->id);
    }

    public function testASharedProductDoesNotTakeTheBarcodeFromCustomFoods(): void
    {
        $this->confirm(self::SPREAD);

        new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex, $this->corrected('Ma pâte', 500)));

        self::assertSame('Ma pâte', $this->scan(self::SPREAD)->food?->name);
        self::assertNull(new FindOwnFoodByBarcodeHandler($this->foods)(new FindOwnFoodByBarcode($this->sam, new Barcode(self::SPREAD))), 'the shared one is nobody\'s own');
    }

    public function testAnEditCannotTakeTheBarcodeOfAnotherOwnFood(): void
    {
        $create = new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods));
        $create(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex, $this->corrected('Skyr', 63)));
        $create(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c2'), $this->alex, $this->corrected('Soda', 40, self::SODA)));

        $this->expectException(BarcodeAlreadyUsed::class);
        new EditCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new EditCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c2'), $this->alex, $this->corrected('Soda', 40)));
    }

    public function testACustomFoodBarcodeNamesOneOfTheUsersFoodsAtMost(): void
    {
        $create = new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods));
        $edit = new EditCustomFoodHandler($this->foods, new OwnBarcodes($this->foods));
        $create(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex, $this->corrected('Skyr', 63)));
        $edit(new EditCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c1'), $this->alex, $this->corrected('Skyr nature', 63)));
        $create(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c2'), $this->sam, $this->corrected('Skyr de Sam', 63)));

        $this->expectException(BarcodeAlreadyUsed::class);
        $create(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000000c3'), $this->alex, $this->corrected('Autre skyr', 60)));
    }

    private function scan(string $barcode, ?UserId $user = null): ScanView
    {
        return new ScanBarcodeHandler($this->foods, $this->provider)(new ScanBarcode($user ?? $this->alex, new Barcode($barcode)));
    }

    private function confirm(string $barcode, string $foodId = '00000000-0000-7000-8000-0000000000f1'): void
    {
        new KeepScannedProductHandler($this->foods, $this->provider)(new KeepScannedProduct(new FoodId($foodId), new Barcode($barcode)));
    }

    /**
     * What the confirm endpoint does with corrections: the user's own food for the code is
     * edited, or one is created.
     */
    private function correct(UserId $user, string $foodId, CustomFoodDetails $details): void
    {
        $barcode = $details->barcode();
        self::assertNotNull($barcode);
        $own = new FindOwnFoodByBarcodeHandler($this->foods)(new FindOwnFoodByBarcode($user, $barcode));
        if (null !== $own) {
            new EditCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new EditCustomFood(new FoodId($own->id), $user, $details));

            return;
        }
        new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new CreateCustomFood(new FoodId($foodId), $user, $details));
    }

    private function corrected(string $name, float $kcal, string $barcode = self::SPREAD): CustomFoodDetails
    {
        return new CustomFoodDetails($name, null, FoodState::NotApplicable, $kcal, 6, 57, 25, barcode: $barcode);
    }
}
