<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Domain\Food\Barcode;

/**
 * A product as an external database describes it, normalised to our units (per 100 g, kcal)
 * but not validated: values may be missing or implausible.
 */
final readonly class ExternalProduct
{
    public function __construct(
        public Barcode $barcode,
        public ?string $name,
        public ?string $brand = null,
        public ?string $category = null,
        public ?float $energyKcal = null,
        public ?float $protein = null,
        public ?float $carbohydrate = null,
        public ?float $fat = null,
        public ?float $fibre = null,
        public ?float $sugars = null,
        public ?float $saturatedFat = null,
        public ?float $salt = null,
        /** One serving as the label states it, when it is in grams. */
        public ?float $servingGrams = null,
        /** The pack, as printed ("400 g"): shown to help the user recognise the product. */
        public ?string $quantity = null,
    ) {
    }
}
