<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Port;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Domain\Food\Barcode;

/**
 * A database of packaged products (Open Food Facts; a fake one in tests and E2E, ADR-0007).
 * What it returns is only a proposal: nothing is saved before the user confirms it.
 */
interface FoodDatabaseProvider
{
    /**
     * @return ExternalProduct|null null when the product is unknown
     *
     * @throws ProductDatabaseUnavailable when the database cannot be reached
     *
     * @phpstan-impure an external lookup: two calls may answer differently
     */
    public function findByBarcode(Barcode $barcode): ?ExternalProduct;
}
