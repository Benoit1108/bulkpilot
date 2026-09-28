<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Barcode;

use App\Nutrition\Domain\Exception\InvalidNutritionFacts;
use App\Nutrition\Domain\Exception\InvalidPortion;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;

/**
 * What the user is asked to confirm: the external values, and whether they can be kept as they are.
 */
final readonly class ProductProposal
{
    /**
     * @param list<string> $missing required values the database does not give
     * @param string|null  $suspect the value that makes the others implausible, when they all are given but do not hold together
     */
    private function __construct(
        public ExternalProduct $product,
        public array $missing,
        public ?string $suspect,
        private ?string $reason,
    ) {
    }

    public static function of(ExternalProduct $product): self
    {
        $missing = array_keys(array_filter([
            'name' => null === $product->name || '' === trim($product->name),
            'energyKcal' => null === $product->energyKcal,
            'protein' => null === $product->protein,
            'carbohydrate' => null === $product->carbohydrate,
            'fat' => null === $product->fat,
        ]));

        $suspect = null;
        $reason = null;
        if ([] === $missing) {
            try {
                self::factsOf($product);
            } catch (InvalidNutritionFacts $e) {
                $suspect = $e->field ?? 'macros';
                $reason = $e->getMessage();
            }
        }

        return new self($product, $missing, $suspect, $reason);
    }

    public function isComplete(): bool
    {
        return [] === $this->missing && null === $this->suspect;
    }

    /**
     * @throws IncompleteProduct
     */
    public function facts(): NutritionFacts
    {
        if (!$this->isComplete()) {
            throw IncompleteProduct::because($this->reason ?? 'missing '.implode(', ', $this->missing));
        }

        return self::factsOf($this->product);
    }

    /**
     * A serving, when the label gives one in grams: the most natural quantity to log.
     *
     * @return list<Portion>
     */
    public function portions(): array
    {
        $grams = $this->product->servingGrams;
        if (null === $grams) {
            return [];
        }
        try {
            return [new Portion('1 portion', $grams)];
        } catch (InvalidPortion) {
            return [];
        }
    }

    public function name(): string
    {
        return trim((string) $this->product->name);
    }

    /**
     * @throws InvalidNutritionFacts
     */
    private static function factsOf(ExternalProduct $product): NutritionFacts
    {
        return new NutritionFacts(
            (float) $product->energyKcal,
            (float) $product->protein,
            (float) $product->carbohydrate,
            (float) $product->fat,
            $product->fibre,
            $product->sugars,
            $product->saturatedFat,
            $product->salt,
        );
    }
}
