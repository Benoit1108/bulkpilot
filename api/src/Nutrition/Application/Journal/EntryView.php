<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\MealSlot;

final readonly class EntryView
{
    public function __construct(
        public string $id,
        public MealSlot $slot,
        public ?string $foodId,
        public ?string $recipeId,
        public string $name,
        public FoodState $state,
        public float $grams,
        public NutrientAmounts $amounts,
        public NutrientAmounts $per100Grams,
        public \DateTimeImmutable $loggedAt,
    ) {
    }

    public static function of(JournalEntry $entry): self
    {
        $food = $entry->food();

        return new self($entry->id()->value, $entry->slot(), $food->foodId?->value, $food->recipeId?->value, $food->name, $food->state, $entry->grams(), $entry->amounts(), $food->per100Grams, $entry->loggedAt());
    }
}
