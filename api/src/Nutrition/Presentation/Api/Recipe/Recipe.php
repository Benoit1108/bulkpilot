<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Nutrition\Application\Recipe\RecipeView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

/**
 * A dish made of foods. Its values follow its foods; journal entries keep those of the day they were logged.
 */
#[ApiResource(
    shortName: 'Recipe',
    operations: [
        new GetCollection(uriTemplate: '/recipes', description: 'The user\'s recipes, by name, with their composition.', paginationEnabled: false, provider: RecipeProvider::class),
        new Get(uriTemplate: '/recipes/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'A recipe, ingredient by ingredient.', provider: RecipeProvider::class),
        new Post(uriTemplate: '/recipes', description: 'Creates a recipe (1 to 50 ingredients, 1 to 50 servings).', input: RecipeInput::class, processor: RecipeProcessor::class),
        new Put(uriTemplate: '/recipes/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Replaces a recipe; entries already logged keep their values.', input: RecipeInput::class, read: false, processor: RecipeProcessor::class),
        new Delete(uriTemplate: '/recipes/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Deletes a recipe; favourite meals made with it keep working.', read: false, processor: RecipeProcessor::class),
    ],
)]
final readonly class Recipe
{
    /**
     * @param list<RecipeIngredient> $ingredients
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public int $servings,
        /** What the cooked dish weighs, when weighed (servings and per 100 g are then on the cooked dish). */
        #[ApiProperty(required: true)]
        public ?float $cookedWeightGrams,
        #[ApiProperty(required: true)]
        public array $ingredients,
        #[ApiProperty(required: true)]
        public float $totalGrams,
        #[ApiProperty(required: true)]
        public float $servingGrams,
        #[ApiProperty(required: true)]
        public Nutrients $total,
        #[ApiProperty(required: true)]
        public Nutrients $perServing,
        #[ApiProperty(required: true)]
        public Nutrients $per100g,
    ) {
    }

    public static function fromView(RecipeView $view): self
    {
        $composition = $view->composition;

        return new self(
            $view->id,
            $view->name,
            $view->servings,
            $view->cookedWeightGrams,
            array_map(RecipeIngredient::fromView(...), $view->ingredients),
            round($composition->totalGrams, 1),
            round($composition->servingGrams, 1),
            Nutrients::of($composition->total),
            Nutrients::of($composition->perServing),
            Nutrients::of($composition->per100Grams),
        );
    }
}
