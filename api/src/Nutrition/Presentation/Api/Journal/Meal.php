<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Journal\MealView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class Meal
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => MealSlots::VALUES])]
        public string $slot,
        /** Percent of the day's calories planned for this meal (0: not planned). */
        #[ApiProperty(required: true)]
        public int $share,
        #[ApiProperty(required: true)]
        public ?int $targetEnergyKcal,
        #[ApiProperty(required: true)]
        public Nutrients $totals,
        #[ApiProperty(required: true)]
        public array $entries,
    ) {
    }

    public static function fromView(MealView $view): self
    {
        return new self($view->slot->value, $view->share, $view->targetEnergyKcal, Nutrients::of($view->totals), array_map(Entry::fromView(...), $view->entries));
    }
}
