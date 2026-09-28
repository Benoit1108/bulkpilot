<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\FoodDatabase;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The provider chosen by FOOD_DATABASE: "openfoodfacts" (cached, within the application's read
 * budget) or "fake" (tests; the production entrypoint refuses it unless ALLOW_FAKE_PROVIDERS=1).
 */
#[AsAlias(FoodDatabaseProvider::class)]
final readonly class ConfiguredFoodDatabaseProvider implements FoodDatabaseProvider
{
    private FoodDatabaseProvider $provider;

    public function __construct(
        #[Autowire('%env(FOOD_DATABASE)%')]
        string $database,
        OpenFoodFactsProvider $openFoodFacts,
        FakeFoodDatabaseProvider $fake,
        #[Autowire(service: 'cache.app')]
        CacheInterface $cache,
        RateLimiterFactoryInterface $openFoodFactsLimiter,
    ) {
        $this->provider = match ($database) {
            'openfoodfacts' => new CachedFoodDatabaseProvider(new RateLimitedFoodDatabaseProvider($openFoodFacts, $openFoodFactsLimiter), $cache),
            'fake' => $fake,
            default => throw new \InvalidArgumentException(\sprintf('FOOD_DATABASE must be "openfoodfacts" or "fake", "%s" given.', $database)),
        };
    }

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        return $this->provider->findByBarcode($barcode);
    }
}
