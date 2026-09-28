<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Food\Portion;
use App\Shared\Domain\Identifier\UserId;

final readonly class FoodView
{
    /**
     * @param list<Portion> $portions
     */
    public function __construct(
        public string $id,
        public FoodSource $source,
        public string $name,
        public ?string $brand,
        public ?string $category,
        public FoodState $state,
        public NutritionFacts $per100Grams,
        public array $portions,
        public bool $editable,
    ) {
    }

    public static function of(Food $food, UserId $viewer): self
    {
        return new self(
            $food->id()->value,
            $food->source(),
            $food->name(),
            $food->brand(),
            $food->category(),
            $food->state(),
            $food->facts(),
            $food->portions(),
            $food->isEditableBy($viewer),
        );
    }

    /**
     * @param iterable<Food> $foods
     *
     * @return list<self>
     */
    public static function list(iterable $foods, UserId $viewer): array
    {
        $views = [];
        foreach ($foods as $food) {
            $views[] = self::of($food, $viewer);
        }

        return $views;
    }
}
