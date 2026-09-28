<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class TargetsTest extends NutritionApiTestCase
{
    private const array PROFILE = [
        'sex' => 'male',
        'birthDate' => '1990-01-15',
        'heightCm' => 180,
        'weightKg' => 80,
        'lifestyle' => 'active',
        'trainingSessionsPerWeek' => 4,
        'goal' => 'bulk',
        'weeklyWeightChangeKg' => 0.25,
    ];

    public function testTheEstimateShowsEachStepAndSavesNothing(): void
    {
        $this->register();

        $estimate = $this->call('POST', '/api/nutrition/targets/estimate', self::PROFILE)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1.5, $estimate['activityLevel']);
        self::assertSame(275, $estimate['dailyAdjustment']);
        self::assertSame(144, $estimate['proteinGrams']);
        self::assertSame(self::int($estimate, 'energyKcal'), 4 * self::int($estimate, 'proteinGrams') + 4 * self::int($estimate, 'carbohydrateGrams') + 9 * self::int($estimate, 'fatGrams'));
        self::assertSame([], $this->call('GET', '/api/nutrition/targets')->toArray());
    }

    public function testAcceptingTheEstimateSetsTheTargetOfTheDay(): void
    {
        $this->register();
        $estimate = $this->call('POST', '/api/nutrition/targets/estimate', self::PROFILE)->toArray();

        $this->call('POST', '/api/nutrition/targets', [
            'effectiveFrom' => $this->today,
            'energyKcal' => $estimate['energyKcal'],
            'proteinGrams' => $estimate['proteinGrams'],
            'carbohydrateGrams' => $estimate['carbohydrateGrams'],
            'fatGrams' => $estimate['fatGrams'],
            'estimate' => self::PROFILE,
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['effectiveFrom' => $this->today, 'source' => 'estimate', 'estimate' => ['basalMetabolicRate' => $estimate['basalMetabolicRate']]]);
        $day = $this->getJson('/api/nutrition/days/'.$this->today);
        self::assertSame($estimate['energyKcal'], self::int($day, 'target.energyKcal'));
        self::assertSame((int) round(self::int($estimate, 'energyKcal') * 0.3), self::int($day, 'meals.1.targetEnergyKcal'));
    }

    public function testManualTargetsMustBeConsistentAndStartTodayOrLater(): void
    {
        $this->register();

        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->today, 'energyKcal' => 3000, 'proteinGrams' => 100, 'carbohydrateGrams' => 100, 'fatGrams' => 50]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.inconsistent_target');

        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => new \DateTimeImmutable($this->today)->modify('-7 days')->format('Y-m-d'), 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_journal_day');

        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->today, 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['source' => 'manual', 'estimate' => null]);
    }

    public function testTheHistoryIsPrivateAndMostRecentFirst(): void
    {
        $this->register();
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->today, 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->today, 'energyKcal' => 2700, 'proteinGrams' => 150, 'carbohydrateGrams' => 350, 'fatGrams' => 75]);

        self::assertSame([2700, 2500], array_column($this->call('GET', '/api/nutrition/targets')->toArray(), 'energyKcal'));

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->call('GET', '/api/nutrition/targets')->toArray());
    }

    public function testImplausibleEstimatesAreRejected(): void
    {
        $this->register();

        $this->call('POST', '/api/nutrition/targets/estimate', ['goal' => 'bulk', 'weeklyWeightChangeKg' => -0.5] + self::PROFILE);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_energy_inputs');

        $this->call('POST', '/api/nutrition/targets/estimate', ['sex' => 'x', 'heightCm' => 20, 'lifestyle' => 'couch'] + self::PROFILE);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'sex'], ['propertyPath' => 'heightCm'], ['propertyPath' => 'lifestyle']]]);
    }

    public function testTheMealSplitIsReadAndReplaced(): void
    {
        $this->register();
        self::assertSame(['breakfast' => 25, 'lunch' => 30, 'snack' => 0, 'gainer' => 15, 'dinner' => 30], $this->call('GET', '/api/nutrition/meal-split')->toArray());

        $this->call('PUT', '/api/nutrition/meal-split', ['breakfast' => 20, 'lunch' => 30, 'snack' => 10, 'gainer' => 10, 'dinner' => 30]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['snack' => 10]);
        self::assertSame(10, self::int($this->getJson('/api/nutrition/days/'.$this->today), 'meals.2.share'));

        $this->call('PUT', '/api/nutrition/meal-split', ['breakfast' => 50, 'lunch' => 0, 'snack' => 0, 'gainer' => 0, 'dinner' => 0]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_meal_split');
    }
}
