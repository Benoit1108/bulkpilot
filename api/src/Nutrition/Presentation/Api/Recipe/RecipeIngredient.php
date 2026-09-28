<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Recipe\IngredientView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class RecipeIngredient
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $foodId,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['raw', 'cooked', 'not_applicable']])]
        public string $state,
        #[ApiProperty(required: true)]
        public float $grams,
        #[ApiProperty(required: true)]
        public Nutrients $nutrients,
        /** False for a custom food deleted since: still counted, no longer searchable. */
        #[ApiProperty(required: true)]
        public bool $available,
    ) {
    }

    public static function fromView(IngredientView $view): self
    {
        return new self($view->foodId, $view->name, $view->state->value, $view->grams, Nutrients::of($view->amounts), $view->available);
    }
}
