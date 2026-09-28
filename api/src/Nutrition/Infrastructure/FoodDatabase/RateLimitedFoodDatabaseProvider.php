<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\FoodDatabase;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Open Food Facts asks for fewer than 100 product reads a minute from one application: all
 * accounts share this budget (per-account limits alone would let a few users exceed it). Placed
 * under the cache, so only actual calls count. Once spent, the database is reported unavailable
 * rather than hammered.
 */
final readonly class RateLimitedFoodDatabaseProvider implements FoodDatabaseProvider
{
    public function __construct(private FoodDatabaseProvider $inner, private RateLimiterFactoryInterface $limiter)
    {
    }

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        if (!$this->limiter->create('open_food_facts')->consume()->isAccepted()) {
            throw ProductDatabaseUnavailable::because('the Open Food Facts read budget is spent for this minute');
        }

        return $this->inner->findByBarcode($barcode);
    }
}
