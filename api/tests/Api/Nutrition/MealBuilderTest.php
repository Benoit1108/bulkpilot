<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class MealBuilderTest extends NutritionApiTestCase
{
    public function testItProposesMealsForWhatIsLeftAndOneIsLoggedInOneGo(): void
    {
        $this->register();
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->today, 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(201);

        $result = $this->call('GET', '/api/nutrition/meal-builder?day='.$this->today.'&slot=lunch')->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame('lunch', self::string($result, 'slot'));
        self::assertSame(750.0, self::number($result, 'remaining.energyKcal'), '30 % of 2500');
        $proposals = self::array($result, 'proposals');
        self::assertNotEmpty($proposals);
        self::assertLessThanOrEqual(750 * 1.1, self::number($proposals, '0.totals.energyKcal'));
        self::assertIsInt(self::at($proposals, '0.gapKcal'));

        $items = array_map(static fn (mixed $item): array => ['foodId' => self::string($item, 'foodId'), 'grams' => self::number($item, 'grams')], self::array($proposals, '0.items'));
        $day = $this->call('POST', '/api/nutrition/entries/batch', ['day' => $this->today, 'slot' => 'lunch', 'items' => $items])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertCount(\count($items), self::array($day, 'meals.1.entries'));
        self::assertEqualsWithDelta(self::number($proposals, '0.totals.energyKcal'), self::number($day, 'totals.energyKcal'), 1);

        $after = $this->call('GET', '/api/nutrition/meal-builder?day='.$this->today.'&slot=lunch')->toArray();
        self::assertLessThan(750 * 0.2, self::number($after, 'remaining.energyKcal'));
    }

    public function testWithoutTargetThereIsNothingToBuild(): void
    {
        $this->register();

        $result = $this->call('GET', '/api/nutrition/meal-builder?day='.$this->today)->toArray();

        self::assertNull(self::at($result, 'remaining'));
        self::assertNull(self::at($result, 'slot'));
        self::assertSame([], self::array($result, 'proposals'));
    }

    public function testImplausibleRequestsAreRejected(): void
    {
        $this->register();

        $this->call('GET', '/api/nutrition/meal-builder?day='.$this->today.'&slot=brunch');
        self::assertResponseStatusCodeSame(422);
        $this->call('GET', '/api/nutrition/meal-builder?day=2026-02-30');
        self::assertResponseStatusCodeSame(422);

        $this->call('POST', '/api/nutrition/entries/batch', ['day' => $this->today, 'slot' => 'lunch', 'items' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'items']]]);

        $rice = $this->foodId('riz blanc cru');
        $this->call('POST', '/api/nutrition/entries/batch', ['day' => $this->today, 'slot' => 'lunch', 'items' => [
            ['foodId' => $rice, 'grams' => 100],
            ['foodId' => '00000000-0000-7000-8000-000000000000', 'grams' => 100],
        ]]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0.0, self::at($this->getJson('/api/nutrition/days/'.$this->today), 'totals.energyKcal'), 'all or nothing');
    }
}
