<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InvalidBarcode;
use App\Nutrition\Domain\Exception\InvalidFood;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class BarcodeTest extends TestCase
{
    #[TestWith(['3017620422003', '3017620422003'])] // EAN-13
    #[TestWith(['96385074', '96385074'])] // EAN-8
    #[TestWith(['036000291452', '0036000291452'])] // UPC-A → its EAN-13
    #[TestWith(['03017620422003', '3017620422003'])] // GTIN-14 of an EAN-13
    #[TestWith(['10614141000415', '10614141000415'])] // GTIN-14 of a case
    #[TestWith([' 3017620-422003 ', '3017620422003'])] // as copied from a label
    #[TestWith(['0000096385074', '96385074'])] // EAN-13 wrapping an EAN-8
    #[TestWith(['00000096385074', '96385074'])] // GTIN-14 wrapping an EAN-8
    public function testValidCodesAreNormalised(string $code, string $normalised): void
    {
        self::assertSame($normalised, new Barcode($code)->value);
    }

    #[TestWith(['3017620422004'])] // wrong check digit: EAN-13
    #[TestWith(['96385075'])] // EAN-8
    #[TestWith(['036000291453'])] // UPC-A
    #[TestWith(['10614141000416'])] // GTIN-14
    #[TestWith(['00000000'])] // only zeros, although the check digit holds
    #[TestWith(['0000000000000'])]
    #[TestWith(['1234567'])]
    #[TestWith(['123456789'])]
    #[TestWith(['301762042200A'])]
    #[TestWith([''])]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectException(InvalidBarcode::class);

        new Barcode($code);
    }

    public function testTheSameProductIsTheSameCode(): void
    {
        self::assertTrue(new Barcode('036000291452')->equals(new Barcode('0036000291452')));
        self::assertFalse(new Barcode('3017620422003')->equals(new Barcode('5449000000996')));
    }

    public function testAScannedProductIsASharedFoodKeyedByItsBarcode(): void
    {
        $barcode = new Barcode('3017620422003');
        $food = Food::fromScannedProduct(new FoodId('00000000-0000-7000-8000-0000000000f1'), $barcode, 'Pâte à tartiner', 'Nutella', 'Pâtes à tartiner', new NutritionFacts(539, 6.3, 57.5, 30.9), [new Portion('1 portion', 15)]);

        self::assertSame(FoodSource::OpenFoodFacts, $food->source());
        self::assertSame('3017620422003', $food->sourceReference());
        self::assertSame($barcode, $food->barcode());
        self::assertNull($food->ownerId());
        self::assertTrue($food->isVisibleTo(new UserId('00000000-0000-7000-8000-000000000001')));
        self::assertFalse($food->isEditableBy(new UserId('00000000-0000-7000-8000-000000000001')));

        $food->refreshFromProduct('Pâte à tartiner, cuite', ' Ferrero ', null, new NutritionFacts(530, 6, 57, 30), []);
        self::assertSame('Ferrero', $food->brand());
        self::assertSame(FoodState::Cooked, $food->state());
        self::assertSame(530.0, $food->facts()->energyKcal);
        self::assertSame([], $food->portions());
    }

    public function testOnlyScannedProductsAreRefreshedFromOpenFoodFacts(): void
    {
        $food = Food::fromCatalogue(new FoodId('00000000-0000-7000-8000-0000000000f1'), FoodSource::Ciqual, '9100', 'Riz', null, new NutritionFacts(350, 7, 78, 1));

        $this->expectException(InvalidFood::class);
        $food->refreshFromProduct('Riz', null, null, new NutritionFacts(350, 7, 78, 1), []);
    }

    public function testScannedProductsAreNotRefreshedFromCiqual(): void
    {
        $food = Food::fromScannedProduct(new FoodId('00000000-0000-7000-8000-0000000000f1'), new Barcode('3017620422003'), 'Pâte', null, null, new NutritionFacts(539, 6.3, 57.5, 30.9), []);

        $this->expectException(InvalidFood::class);
        $food->refreshFromCatalogue('Pâte', null, new NutritionFacts(539, 6.3, 57.5, 30.9));
    }

    public function testACustomFoodCarriesAndChangesItsBarcode(): void
    {
        $owner = new UserId('00000000-0000-7000-8000-000000000001');
        $food = Food::createCustom(new FoodId('00000000-0000-7000-8000-0000000000f1'), $owner, 'Skyr', null, FoodState::NotApplicable, new NutritionFacts(63, 11, 4, 0.2), [], new Barcode('5449000000996'));
        self::assertSame('5449000000996', $food->barcode()?->value);

        $food->edit('Skyr', null, FoodState::NotApplicable, new NutritionFacts(63, 11, 4, 0.2), [], null);
        self::assertNull($food->barcode(), 'a label without barcode');
    }
}
