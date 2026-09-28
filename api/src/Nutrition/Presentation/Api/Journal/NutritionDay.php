<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Nutrition\Application\Journal\DayView;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Nutrition\Presentation\Api\Shared\Nutrients;
use App\Nutrition\Presentation\Api\Target\NutritionTarget;

/**
 * A day of the food journal, meal by meal, against the target in force that day.
 * Journal writes (JournalEntry) answer with the day they changed, so the screen is redrawn from one response.
 */
#[ApiResource(
    shortName: 'NutritionDay',
    operations: [
        new Get(
            uriTemplate: '/nutrition/days/{day}',
            requirements: ['day' => Days::PATTERN],
            description: 'The journal of a calendar day (YYYY-MM-DD, the user\'s local day).',
            provider: NutritionDayProvider::class,
        ),
    ],
)]
final readonly class NutritionDay
{
    /**
     * @param list<Meal> $meals every meal slot, in chronological order
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(required: true)]
        public Nutrients $totals,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?NutritionTarget $target,
        #[ApiProperty(required: true)]
        public array $meals,
    ) {
    }

    public static function fromView(DayView $view): self
    {
        return new self(
            $view->day->format('Y-m-d'),
            Nutrients::of($view->totals),
            null === $view->target ? null : NutritionTarget::fromView($view->target),
            array_map(Meal::fromView(...), $view->meals),
        );
    }
}
