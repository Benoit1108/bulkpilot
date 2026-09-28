<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Exception\InvalidBarcode;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;

/**
 * What the user types for a custom food (label of a packaged product, or a food missing from CIQUAL).
 */
final readonly class CustomFoodDetails
{
    /**
     * @param list<array{label: string, grams: float}> $portions
     */
    public function __construct(
        public string $name,
        public ?string $brand,
        public FoodState $state,
        public float $energyKcal,
        public float $protein,
        public float $carbohydrate,
        public float $fat,
        public ?float $fibre = null,
        public ?float $sugars = null,
        public ?float $saturatedFat = null,
        public ?float $salt = null,
        public array $portions = [],
        /** As printed under the bars, when the food is a packaged product. */
        public ?string $barcode = null,
    ) {
    }

    /**
     * @throws InvalidBarcode
     */
    public function barcode(): ?Barcode
    {
        return null === $this->barcode || '' === trim($this->barcode) ? null : new Barcode($this->barcode);
    }

    public function facts(): NutritionFacts
    {
        return new NutritionFacts($this->energyKcal, $this->protein, $this->carbohydrate, $this->fat, $this->fibre, $this->sugars, $this->saturatedFat, $this->salt);
    }

    /**
     * @return list<Portion>
     */
    public function portions(): array
    {
        return array_map(static fn (array $portion): Portion => new Portion($portion['label'], $portion['grams']), $this->portions);
    }
}
