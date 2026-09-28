<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Nutrition\Infrastructure\FoodDatabase\FakeFoodDatabaseProvider;
use App\Tests\Support\Api\NutritionApiTestCase;

/**
 * Against the fake product database (FOOD_DATABASE=fake in the test environment).
 */
final class BarcodeTest extends NutritionApiTestCase
{
    public function testAScannedProductIsProposedThenKeptAndKnownToEveryone(): void
    {
        $this->register();

        $scan = $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::SPREAD])->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('found', self::string($scan, 'status'));
        self::assertNull(self::at($scan, 'food'));
        self::assertSame(['name' => 'Pâte à tartiner aux noisettes et au cacao', 'brand' => 'Nutella', 'quantity' => '400 g', 'energyKcal' => 539.0], array_intersect_key(self::array($scan, 'product'), ['name' => 1, 'brand' => 1, 'quantity' => 1, 'energyKcal' => 1]));
        self::assertTrue(self::at($scan, 'product.complete'));
        self::assertSame([], $this->getJson('/api/foods?q=nutella'), 'a scan saves nothing');

        $food = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('off', self::string($food, 'source'));
        self::assertSame(FakeFoodDatabaseProvider::SPREAD, self::string($food, 'barcode'));
        self::assertFalse(self::at($food, 'editable'));
        self::assertSame('1 portion', self::string($food, 'portions.0.label'));

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        $known = $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '3017620-422003'])->toArray();
        self::assertSame('known', self::string($known, 'status'));
        self::assertSame(self::string($food, 'id'), self::string($known, 'food.id'));
        self::assertSame('Pâte à tartiner aux noisettes et au cacao', self::string($this->getJson('/api/foods?q=nutella'), '0.name'));
    }

    public function testAnIncompleteProductIsCompletedIntoTheUsersOwnFood(): void
    {
        $this->register();

        $scan = $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::INCOMPLETE])->toArray();
        self::assertFalse(self::at($scan, 'product.complete'));
        self::assertSame(['protein', 'carbohydrate', 'fat'], self::array($scan, 'product.missing'));

        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::INCOMPLETE]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.incomplete_product');

        $food = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::INCOMPLETE, 'corrections' => [
            'name' => 'Barre protéinée', 'energyKcal' => 350, 'protein' => 30, 'carbohydrate' => 35, 'fat' => 9,
        ]])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('custom', self::string($food, 'source'));
        self::assertTrue(self::at($food, 'editable'));
        self::assertSame(FakeFoodDatabaseProvider::INCOMPLETE, self::string($food, 'barcode'));
        self::assertSame('known', self::string($this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::INCOMPLETE])->toArray(), 'status'));
    }

    public function testImplausibleUnknownAndUnreachableProductsAreReported(): void
    {
        $this->register();

        $implausible = $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::IMPLAUSIBLE])->toArray();
        self::assertFalse(self::at($implausible, 'product.complete'));
        self::assertSame('macros', self::at($implausible, 'product.suspect'));

        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '2000000000046']);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.product_not_found');

        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::UNAVAILABLE]);
        self::assertResponseStatusCodeSame(503);
        self::assertProblemCode('nutrition.product_database_unavailable');

        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '3017620422004']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_barcode');

        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => 'pas un code']);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'barcode']]]);
    }

    public function testACustomFoodCanCarryABarcodeItsOwnerCannotReuse(): void
    {
        $this->register();
        $body = ['name' => 'Skyr', 'energyKcal' => 63, 'protein' => 11, 'carbohydrate' => 4, 'fat' => 0.2, 'barcode' => FakeFoodDatabaseProvider::SODA];

        $food = $this->call('POST', '/api/foods', $body)->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame(FakeFoodDatabaseProvider::SODA, self::string($food, 'barcode'));
        self::assertSame('Skyr', self::string($this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => FakeFoodDatabaseProvider::SODA])->toArray(), 'food.name'), 'the own food before Open Food Facts');

        $this->call('POST', '/api/foods', ['name' => 'Autre'] + $body);
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('nutrition.barcode_already_used');
    }

    public function testCorrectingAgainUpdatesTheUsersOwnFoodWhileOthersKeepTheSharedOne(): void
    {
        $this->register();
        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD]);
        $corrections = ['name' => 'Ma pâte', 'energyKcal' => 500, 'protein' => 6, 'carbohydrate' => 57, 'fat' => 25];

        $mine = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD, 'corrections' => $corrections])->toArray();
        self::assertSame('custom', self::string($mine, 'source'));
        $again = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD, 'corrections' => ['name' => 'Ma pâte allégée'] + $corrections])->toArray();
        self::assertSame(self::string($mine, 'id'), self::string($again, 'id'), 'one own food per barcode');
        self::assertSame('Ma pâte allégée', self::string($again, 'name'));

        $shared = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD])->toArray();
        self::assertSame('off', self::string($shared, 'source'), 'confirming as is answers the shared product, not the own one');

        $this->call('DELETE', '/api/foods/'.self::string($mine, 'id'));
        $fresh = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD, 'corrections' => $corrections])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame(self::string($mine, 'id'), self::string($fresh, 'id'), 'a deleted food is not brought back');
    }

    public function testCorrectionsAreForTheScannedCodeAndCheckedLikeAnyFood(): void
    {
        $this->register();
        $corrections = ['name' => 'Soda', 'energyKcal' => 42, 'protein' => 0, 'carbohydrate' => 10.6, 'fat' => 0];

        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SODA, 'corrections' => ['barcode' => FakeFoodDatabaseProvider::SPREAD] + $corrections]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_barcode');

        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SODA, 'corrections' => ['energyKcal' => -1] + $corrections]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'corrections.energyKcal']]]);

        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SODA, 'corrections' => ['barcode' => '5449-000-000996'] + $corrections]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testScansAndConfirmationsShareAPerAccountBudget(): void
    {
        $this->register();

        for ($i = 0; $i < 29; ++$i) {
            $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '2000000000046']);
            self::assertResponseStatusCodeSame(404);
        }
        $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => '2000000000046']);
        self::assertResponseStatusCodeSame(404);

        $throttled = $this->call('POST', '/api/nutrition/barcode/confirm', ['barcode' => FakeFoodDatabaseProvider::SPREAD]);
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) ($throttled->getHeaders(false)['retry-after'][0] ?? 0));
        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '2000000000046']);
        self::assertResponseStatusCodeSame(429);

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        $this->call('POST', '/api/nutrition/barcode/scan', ['barcode' => '2000000000046']);
        self::assertResponseStatusCodeSame(404, 'each account has its own budget');
    }
}
