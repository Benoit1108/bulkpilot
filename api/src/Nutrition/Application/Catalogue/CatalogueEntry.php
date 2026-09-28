<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Catalogue;

/**
 * One food of a reference table, per 100 g; a null value is unknown in the source.
 */
final readonly class CatalogueEntry
{
    public function __construct(
        public string $reference,
        public string $name,
        public ?string $category,
        public ?float $energyKcal,
        public ?float $protein,
        public ?float $carbohydrate,
        public ?float $fat,
        public ?float $fibre,
        public ?float $sugars,
        public ?float $saturatedFat,
        public ?float $salt,
    ) {
    }
}
