<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Percent of the day's calories planned per meal; the five shares add up to 100.
 */
#[ApiResource(
    shortName: 'MealSplit',
    operations: [
        new Get(uriTemplate: '/nutrition/meal-split', description: 'How the daily calories are spread over meals.', provider: MealSplitState::class),
        new Put(
            uriTemplate: '/nutrition/meal-split',
            description: 'Replaces the meal split (whole percents adding up to 100).',
            read: false,
            processor: MealSplitState::class,
        ),
    ],
)]
final class MealSplit
{
    #[ApiProperty(required: true)]
    #[Assert\Range(min: 0, max: 100)]
    public int $breakfast = 0;

    #[ApiProperty(required: true)]
    #[Assert\Range(min: 0, max: 100)]
    public int $lunch = 0;

    #[ApiProperty(required: true)]
    #[Assert\Range(min: 0, max: 100)]
    public int $snack = 0;

    #[ApiProperty(required: true)]
    #[Assert\Range(min: 0, max: 100)]
    public int $gainer = 0;

    #[ApiProperty(required: true)]
    #[Assert\Range(min: 0, max: 100)]
    public int $dinner = 0;

    /**
     * @param array<string, int> $shares
     */
    public static function of(array $shares): self
    {
        $split = new self();
        $split->breakfast = $shares['breakfast'] ?? 0;
        $split->lunch = $shares['lunch'] ?? 0;
        $split->snack = $shares['snack'] ?? 0;
        $split->gainer = $shares['gainer'] ?? 0;
        $split->dinner = $shares['dinner'] ?? 0;

        return $split;
    }

    /**
     * @return array{breakfast: int, lunch: int, snack: int, gainer: int, dinner: int}
     */
    public function shares(): array
    {
        return ['breakfast' => $this->breakfast, 'lunch' => $this->lunch, 'snack' => $this->snack, 'gainer' => $this->gainer, 'dinner' => $this->dinner];
    }
}
