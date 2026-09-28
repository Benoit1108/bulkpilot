<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\Nutrition\Application\Journal\AveragesView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;
use App\Nutrition\Presentation\Api\Target\NutritionTarget;

/**
 * Daily averages over the logged days of the last 7 or 30 days: a day with nothing logged is
 * left out rather than counted as a day without eating.
 */
#[ApiResource(
    shortName: 'NutritionAverages',
    operations: [
        new Get(
            uriTemplate: '/nutrition/averages',
            description: 'Daily averages over the last 7 or 30 days (today included), over the days that were logged.',
            provider: NutritionAveragesProvider::class,
            parameters: [
                'days' => new QueryParameter(description: '7 or 30.', required: true, schema: ['type' => 'integer']),
                'today' => new QueryParameter(description: "The user's local day, YYYY-MM-DD.", required: true, schema: ['type' => 'string', 'format' => 'date']),
            ],
        ),
    ],
)]
final readonly class NutritionAverages
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public int $days,
        #[ApiProperty(required: true)]
        public int $loggedDays,
        #[ApiProperty(required: true)]
        public ?Nutrients $dailyAverage,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?NutritionTarget $currentTarget,
    ) {
    }

    public static function fromView(AveragesView $view): self
    {
        return new self(
            $view->days,
            $view->loggedDays,
            null === $view->dailyAverage ? null : Nutrients::of($view->dailyAverage),
            null === $view->currentTarget ? null : NutritionTarget::fromView($view->currentTarget),
        );
    }
}
