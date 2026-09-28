<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use App\Nutrition\Application\Recipe\RecipeDetails;
use App\Nutrition\Domain\Recipe\Recipe;
use Symfony\Component\Validator\Constraints as Assert;

final class RecipeInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Recipe::MAX_NAME_LENGTH, normalizer: 'trim')]
    public string $name = '';

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: Recipe::MAX_SERVINGS)]
    public ?int $servings = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(Recipe::MAX_COOKED_GRAMS)]
    public ?float $cookedWeightGrams = null;

    /** @var list<IngredientInput> */
    #[Assert\Count(min: 1, max: Recipe::MAX_INGREDIENTS)]
    #[Assert\Valid]
    public array $ingredients = [];

    public function details(): RecipeDetails
    {
        return new RecipeDetails(
            $this->name,
            (int) $this->servings,
            $this->cookedWeightGrams,
            array_map(static fn (IngredientInput $ingredient): array => ['foodId' => $ingredient->foodId, 'grams' => (float) $ingredient->grams], $this->ingredients),
        );
    }
}
