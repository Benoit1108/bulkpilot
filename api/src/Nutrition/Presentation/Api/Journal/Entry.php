<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Journal\EntryView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class Entry
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => MealSlots::VALUES])]
        public string $slot,
        /** The food it was logged from; null when it came from a recipe. */
        #[ApiProperty(required: true)]
        public ?string $foodId,
        /** The recipe it was logged from; null when it came from a food. */
        #[ApiProperty(required: true)]
        public ?string $recipeId,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['raw', 'cooked', 'not_applicable']])]
        public string $state,
        #[ApiProperty(required: true)]
        public float $grams,
        #[ApiProperty(required: true)]
        public Nutrients $nutrients,
        /** The values it was logged with, per 100 g (frozen at logging time). */
        #[ApiProperty(required: true)]
        public Nutrients $per100g,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $loggedAt,
    ) {
    }

    public static function fromView(EntryView $view): self
    {
        return new self($view->id, $view->slot->value, $view->foodId, $view->recipeId, $view->name, $view->state->value, $view->grams, Nutrients::of($view->amounts), Nutrients::of($view->per100Grams), $view->loggedAt);
    }
}
