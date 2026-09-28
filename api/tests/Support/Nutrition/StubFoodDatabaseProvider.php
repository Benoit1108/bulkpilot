<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;

final class StubFoodDatabaseProvider implements FoodDatabaseProvider
{
    /** @var array<array-key, ExternalProduct> by barcode (numeric strings become int keys) */
    public array $products = [];
    public int $calls = 0;
    /** Every read fails, as during an outage. */
    public bool $down = false;

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        ++$this->calls;
        if ($this->down) {
            throw ProductDatabaseUnavailable::because('stub outage');
        }

        return $this->products[$barcode->value] ?? null;
    }
}
