<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Builder;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Nutrition\Application\Builder\MealBuilderView;
use App\Nutrition\Presentation\Api\Journal\MealSlots;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

/**
 * Meal Builder (spec §28): what is left, and meals that fill it. Add one with POST /nutrition/entries/batch.
 */
#[ApiResource(
    shortName: 'MealBuilder',
    operations: [
        new Get(
            uriTemplate: '/nutrition/meal-builder',
            description: 'Up to 3 meals, from the user\'s usual foods and a few staples, that fill what is left of the day (or of one meal, per the meal split).',
            provider: MealBuilderProvider::class,
            parameters: [
                'day' => new QueryParameter(description: 'YYYY-MM-DD, the user\'s local day.', required: true, schema: ['type' => 'string', 'pattern' => '^'.Days::PATTERN.'$']),
                'slot' => new QueryParameter(description: 'For this meal\'s share of the target; the rest of the day otherwise.', schema: ['type' => 'string', 'enum' => MealSlots::VALUES]),
            ],
        ),
    ],
)]
final readonly class MealBuilderResult
{
    /**
     * @param list<Proposal> $proposals
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $day,
        #[ApiProperty(required: true, schema: ['type' => ['string', 'null'], 'enum' => [...MealSlots::VALUES, null]])]
        public ?string $slot,
        /** Null when the day has no target. */
        #[ApiProperty(required: true)]
        public ?Nutrients $remaining,
        #[ApiProperty(required: true)]
        public array $proposals,
    ) {
    }

    public static function fromView(string $day, MealBuilderView $view): self
    {
        $goal = $view->goal;

        return new self(
            $day,
            $view->slot?->value,
            null === $goal ? null : new Nutrients(round($goal->energyKcal), round($goal->protein), round($goal->carbohydrate), round($goal->fat), null),
            array_map(Proposal::of(...), $view->proposals),
        );
    }
}
