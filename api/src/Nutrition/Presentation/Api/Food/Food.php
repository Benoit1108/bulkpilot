<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Application\Food\SearchFoods;
use App\Nutrition\Presentation\Api\Barcode\ConfirmProductInput;
use App\Nutrition\Presentation\Api\Barcode\ConfirmProductProcessor;

#[ApiResource(
    shortName: 'Food',
    operations: [
        new GetCollection(
            uriTemplate: '/foods',
            description: 'Searches the foods the user can log (CIQUAL and their own), best matches first.',
            paginationEnabled: false,
            provider: FoodProvider::class,
            parameters: [
                'q' => new QueryParameter(description: 'Words of the name, accents and case ignored (2 characters at least).', required: true, schema: ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]),
                'limit' => new QueryParameter(description: 'At most '.SearchFoods::MAX_RESULTS.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => SearchFoods::MAX_RESULTS, 'default' => SearchFoods::DEFAULT_RESULTS]),
            ],
            name: 'foods_search',
        ),
        new GetCollection(
            uriTemplate: '/foods/recent',
            description: 'Foods the user logged lately, most recent first.',
            paginationEnabled: false,
            provider: FoodProvider::class,
            name: 'foods_recent',
        ),
        new GetCollection(
            uriTemplate: '/foods/custom',
            description: "The user's own foods, by name.",
            paginationEnabled: false,
            provider: FoodProvider::class,
            name: 'foods_custom',
        ),
        new Get(
            uriTemplate: '/foods/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'A catalogue food, or one of the user\'s own.',
            provider: FoodProvider::class,
        ),
        new Post(
            uriTemplate: '/nutrition/barcode/confirm',
            description: 'Keeps a scanned product: as Open Food Facts gives it (shared, values read again from the source), or corrected (the user\'s own food, with its barcode).',
            input: ConfirmProductInput::class,
            processor: ConfirmProductProcessor::class,
            name: 'barcode_confirm',
        ),
        new Post(
            uriTemplate: '/foods',
            description: 'Creates a custom food (a packaged product, a food missing from CIQUAL).',
            input: CustomFoodInput::class,
            processor: CustomFoodProcessor::class,
        ),
        new Put(
            uriTemplate: '/foods/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Replaces a custom food. Past journal entries keep the values they were logged with.',
            input: CustomFoodInput::class,
            read: false,
            processor: CustomFoodProcessor::class,
        ),
        new Delete(
            uriTemplate: '/foods/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Deletes a custom food; past journal entries are kept.',
            read: false,
            processor: CustomFoodProcessor::class,
        ),
    ],
)]
final readonly class Food
{
    /**
     * @param list<FoodPortion> $portions
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['ciqual', 'custom', 'off']])]
        public string $source,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public ?string $brand,
        #[ApiProperty(required: true)]
        public ?string $category,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['raw', 'cooked', 'not_applicable']])]
        public string $state,
        #[ApiProperty(required: true)]
        public Composition $per100g,
        #[ApiProperty(required: true)]
        public array $portions,
        #[ApiProperty(required: true)]
        public bool $editable,
        #[ApiProperty(required: true)]
        public ?string $barcode,
    ) {
    }

    public static function fromView(FoodView $view): self
    {
        return new self(
            $view->id,
            $view->source->value,
            $view->name,
            $view->brand,
            $view->category,
            $view->state->value,
            Composition::of($view->per100Grams),
            array_map(FoodPortion::of(...), $view->portions),
            $view->editable,
            $view->barcode,
        );
    }
}
