<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use App\Nutrition\Domain\Recipe\Ingredient;
use Symfony\Component\Validator\Constraints as Assert;

final class IngredientInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $foodId = '';

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(Ingredient::MAX_GRAMS)]
    public ?float $grams = null;
}
