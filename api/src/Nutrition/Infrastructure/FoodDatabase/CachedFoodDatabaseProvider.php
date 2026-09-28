<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\FoodDatabase;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Scanning then confirming reads the same product twice: one call to the external database.
 * Found products are kept a day, unknown codes an hour; failures are not kept.
 */
final readonly class CachedFoodDatabaseProvider implements FoodDatabaseProvider
{
    public const int FOUND_TTL = 86400;
    public const int NOT_FOUND_TTL = 3600;

    public function __construct(private FoodDatabaseProvider $inner, private CacheInterface $cache)
    {
    }

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        /** @var array{product: ExternalProduct|null} $entry */
        $entry = $this->cache->get('food_database.'.$barcode->value, function (ItemInterface $item) use ($barcode): array {
            $product = $this->inner->findByBarcode($barcode);
            $item->expiresAfter(null === $product ? self::NOT_FOUND_TTL : self::FOUND_TTL);

            return ['product' => $product];
        });

        return $entry['product'];
    }
}
