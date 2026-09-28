<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Infrastructure;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Infrastructure\FoodDatabase\CachedFoodDatabaseProvider;
use App\Nutrition\Infrastructure\FoodDatabase\ConfiguredFoodDatabaseProvider;
use App\Nutrition\Infrastructure\FoodDatabase\FakeFoodDatabaseProvider;
use App\Nutrition\Infrastructure\FoodDatabase\OpenFoodFactsProvider;
use App\Nutrition\Infrastructure\FoodDatabase\RateLimitedFoodDatabaseProvider;
use App\Tests\Support\Nutrition\StubFoodDatabaseProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class FoodDatabaseTest extends TestCase
{
    private const string SPREAD = '3017620422003';

    public function testAProductIsReadFromOpenFoodFactsAndNormalised(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = ['url' => $url, 'headers' => $options['headers']];

            return new JsonMockResponse(['status' => 1, 'product' => [
                'code' => self::SPREAD,
                'product_name' => 'Nutella',
                'product_name_fr' => ' Pâte à tartiner  aux noisettes ',
                'brands' => 'Nutella,Ferrero',
                'categories' => 'Petit-déjeuners, Produits à tartiner, Pâtes à tartiner au chocolat',
                'quantity' => '400 g',
                'serving_quantity' => '15',
                'serving_quantity_unit' => 'g',
                'nutriments' => ['energy-kcal_100g' => 539, 'proteins_100g' => '6,3', 'carbohydrates_100g' => 57.5, 'fat_100g' => 30.9, 'sugars_100g' => 56.3, 'saturated-fat_100g' => 10.6, 'salt_100g' => 0.107],
            ]]);
        });

        $product = new OpenFoodFactsProvider($client, 'BulkPilot/test (ci)')->findByBarcode(new Barcode(self::SPREAD));

        self::assertNotNull($product);
        self::assertSame('Pâte à tartiner aux noisettes', $product->name);
        self::assertSame('Nutella', $product->brand);
        self::assertSame('Pâtes à tartiner au chocolat', $product->category);
        self::assertSame([539.0, 6.3, 57.5, 30.9, null], [$product->energyKcal, $product->protein, $product->carbohydrate, $product->fat, $product->fibre]);
        self::assertSame(15.0, $product->servingGrams);
        self::assertSame('400 g', $product->quantity);
        self::assertStringStartsWith('https://world.openfoodfacts.org/api/v2/product/3017620422003.json?', $requests[0]['url']);
        self::assertStringContainsString('fields=', $requests[0]['url']);
        $headers = $requests[0]['headers'];
        self::assertIsArray($headers);
        self::assertContains('User-Agent: BulkPilot/test (ci)', $headers);
    }

    public function testEnergyGivenInKilojoulesIsConverted(): void
    {
        $product = OpenFoodFactsProvider::normalise(new Barcode(self::SPREAD), ['product_name' => 'Soda', 'serving_quantity' => 33, 'serving_quantity_unit' => 'cl', 'nutriments' => ['energy-kj_100g' => 180]]);

        self::assertSame(43.0, $product->energyKcal);
        self::assertNull($product->servingGrams, 'a serving in centilitres is not in grams');
        self::assertNull($product->brand);
        self::assertNull($product->category);
    }

    public function testEntryMistakesAreUnknownRatherThanZero(): void
    {
        $product = OpenFoodFactsProvider::normalise(new Barcode(self::SPREAD), ['nutriments' => ['energy-kcal_100g' => 'INF', 'proteins_100g' => -1, 'fat_100g' => 'NAN', 'carbohydrates_100g' => '1e400', 'salt_100g' => 'n/a']]);

        self::assertSame([null, null, null, null, null], [$product->energyKcal, $product->protein, $product->fat, $product->carbohydrate, $product->salt]);
    }

    /**
     * @param array<string, mixed> $serving
     */
    #[TestWith([['serving_quantity' => 30, 'serving_quantity_unit' => 'G'], 30.0])]
    #[TestWith([['serving_quantity' => 30, 'serving_size' => '1 bol (30 g)'], 30.0])]
    #[TestWith([['serving_quantity' => '30', 'serving_size' => '30g'], 30.0])]
    #[TestWith([['serving_quantity' => 250, 'serving_size' => '250 ml'], null])]
    #[TestWith([['serving_quantity' => 250], null])]
    #[TestWith([['serving_quantity' => 0, 'serving_quantity_unit' => 'g'], null])]
    #[TestWith([['serving_quantity' => 'INF', 'serving_quantity_unit' => 'g'], null])]
    public function testAServingIsKeptOnlyWhenItIsInGrams(array $serving, ?float $grams): void
    {
        self::assertSame($grams, OpenFoodFactsProvider::normalise(new Barcode(self::SPREAD), $serving)->servingGrams);
    }

    public function testAnUnknownProductIsNull(): void
    {
        $notFound = new OpenFoodFactsProvider(new MockHttpClient(new JsonMockResponse(['status' => 0], ['http_code' => 404])), 'ua');
        $statusZero = new OpenFoodFactsProvider(new MockHttpClient(new JsonMockResponse(['status' => 0, 'status_verbose' => 'product not found'])), 'ua');

        self::assertNull($notFound->findByBarcode(new Barcode(self::SPREAD)));
        self::assertNull($statusZero->findByBarcode(new Barcode(self::SPREAD)));
    }

    public function testAnOutageIsReportedAsUnavailable(): void
    {
        foreach ([new MockResponse('', ['http_code' => 503]), new MockResponse('not json'), new MockResponse('', ['error' => 'timeout'])] as $response) {
            try {
                new OpenFoodFactsProvider(new MockHttpClient($response), 'ua')->findByBarcode(new Barcode(self::SPREAD));
                self::fail('the outage is reported');
            } catch (ProductDatabaseUnavailable $e) {
                self::assertSame('nutrition.product_database_unavailable', $e->errorCode());
            }
        }

        $this->expectException(ProductDatabaseUnavailable::class);
        new OpenFoodFactsProvider(new MockHttpClient(static fn () => throw new TransportException('down')), 'ua')->findByBarcode(new Barcode(self::SPREAD));
    }

    public function testProductsAreCachedButFailuresAreNot(): void
    {
        $inner = new StubFoodDatabaseProvider();
        $inner->products[self::SPREAD] = new ExternalProduct(new Barcode(self::SPREAD), 'Nutella');
        $cached = new CachedFoodDatabaseProvider($inner, new ArrayAdapter());

        self::assertSame('Nutella', $cached->findByBarcode(new Barcode(self::SPREAD))?->name);
        self::assertSame('Nutella', $cached->findByBarcode(new Barcode(self::SPREAD))?->name);
        self::assertNull($cached->findByBarcode(new Barcode(FakeFoodDatabaseProvider::SODA)));
        self::assertNull($cached->findByBarcode(new Barcode(FakeFoodDatabaseProvider::SODA)));
        self::assertSame(2, $inner->calls);

        $inner->down = true;
        foreach ([1, 2] as $attempt) {
            try {
                $cached->findByBarcode(new Barcode(FakeFoodDatabaseProvider::INCOMPLETE));
                self::fail('the outage is reported');
            } catch (ProductDatabaseUnavailable) {
            }
        }
        self::assertSame(4, $inner->calls, 'an outage is asked again next time');
    }

    public function testFoundProductsAreKeptADayAndUnknownCodesAnHour(): void
    {
        $clock = new MockClock();
        $inner = new StubFoodDatabaseProvider();
        $inner->products[self::SPREAD] = new ExternalProduct(new Barcode(self::SPREAD), 'Nutella');
        $cached = new CachedFoodDatabaseProvider($inner, new ArrayAdapter(clock: $clock));
        $read = static function () use ($cached): void {
            $cached->findByBarcode(new Barcode(self::SPREAD));
            $cached->findByBarcode(new Barcode(FakeFoodDatabaseProvider::SODA));
        };

        $read();
        $clock->sleep(CachedFoodDatabaseProvider::NOT_FOUND_TTL - 1);
        $read();
        self::assertSame(2, $inner->calls);

        $clock->sleep(2);
        $read();
        self::assertSame(3, $inner->calls, 'an unknown code is asked again after an hour');

        $clock->sleep(CachedFoodDatabaseProvider::FOUND_TTL - CachedFoodDatabaseProvider::NOT_FOUND_TTL);
        $read();
        self::assertSame(5, $inner->calls, 'a found product after a day');
    }

    public function testAllAccountsShareOneReadBudget(): void
    {
        $inner = new StubFoodDatabaseProvider();
        $limited = new RateLimitedFoodDatabaseProvider($inner, new RateLimiterFactory(['id' => 'open_food_facts', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()));

        $limited->findByBarcode(new Barcode(self::SPREAD));
        $limited->findByBarcode(new Barcode(FakeFoodDatabaseProvider::SODA));

        try {
            $limited->findByBarcode(new Barcode(self::SPREAD));
            self::fail('the budget is spent');
        } catch (ProductDatabaseUnavailable $e) {
            self::assertSame('nutrition.product_database_unavailable', $e->errorCode());
        }
        self::assertSame(2, $inner->calls, 'no call beyond the budget');
    }

    public function testTheProviderIsChosenByConfiguration(): void
    {
        $off = new OpenFoodFactsProvider(new MockHttpClient(new JsonMockResponse(['status' => 1, 'product' => ['product_name' => 'Réel']])), 'ua');
        $fake = new FakeFoodDatabaseProvider();

        $budget = new RateLimiterFactory(['id' => 'open_food_facts', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $configured = new ConfiguredFoodDatabaseProvider('openfoodfacts', $off, $fake, new ArrayAdapter(), $budget);

        self::assertSame('Réel', $configured->findByBarcode(new Barcode(self::SPREAD))?->name);
        self::assertSame('Réel', $configured->findByBarcode(new Barcode(self::SPREAD))?->name, 'cached reads do not spend the budget');
        self::assertSame('Nutella', new ConfiguredFoodDatabaseProvider('fake', $off, $fake, new ArrayAdapter(), $budget)->findByBarcode(new Barcode(self::SPREAD))?->brand);

        $this->expectException(\InvalidArgumentException::class);
        new ConfiguredFoodDatabaseProvider('other', $off, $fake, new ArrayAdapter(), $budget);
    }

    public function testTheFakeDatabaseCoversEveryCase(): void
    {
        $fake = new FakeFoodDatabaseProvider();

        self::assertSame(42.0, $fake->findByBarcode(new Barcode(FakeFoodDatabaseProvider::SODA))?->energyKcal);
        self::assertNull($fake->findByBarcode(new Barcode(FakeFoodDatabaseProvider::INCOMPLETE))?->protein);
        self::assertSame(60.0, $fake->findByBarcode(new Barcode(FakeFoodDatabaseProvider::IMPLAUSIBLE))?->protein);
        self::assertNull($fake->findByBarcode(new Barcode('2000000000046')));
        self::assertSame('Produit test 2912345678906', $fake->findByBarcode(new Barcode(FakeFoodDatabaseProvider::GENERATED_PREFIX.'12345678906'))?->name, 'any in-store code is a new product');

        $this->expectException(ProductDatabaseUnavailable::class);
        $fake->findByBarcode(new Barcode(FakeFoodDatabaseProvider::UNAVAILABLE));
    }
}
