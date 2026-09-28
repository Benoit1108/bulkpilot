<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class JournalTest extends NutritionApiTestCase
{
    public function testAnEmptyDayListsEveryMeal(): void
    {
        $this->register();

        $day = $this->call('GET', '/api/nutrition/days/'.$this->today)->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame($this->today, $day['day']);
        self::assertNull($day['target']);
        self::assertSame(['energyKcal' => 0.0, 'protein' => 0.0, 'carbohydrate' => 0.0, 'fat' => 0.0, 'fibre' => null], $day['totals']);
        self::assertSame(['breakfast', 'lunch', 'snack', 'gainer', 'dinner'], array_column(self::array($day, 'meals'), 'slot'));
        self::assertSame([25, 30, 0, 15, 30], array_column(self::array($day, 'meals'), 'share'));
    }

    public function testLoggingAnswersWithTheUpdatedDay(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');

        $day = $this->logFood($rice, 150);

        self::assertSame('lunch', self::string($day, 'meals.1.slot'));
        self::assertCount(1, self::array($day, 'meals.1.entries'));
        self::assertSame(['slot' => 'lunch', 'foodId' => $rice, 'name' => 'Riz blanc, cru', 'state' => 'raw', 'grams' => 150.0], array_intersect_key(self::array($day, 'meals.1.entries.0'), ['slot' => 1, 'foodId' => 1, 'name' => 1, 'state' => 1, 'grams' => 1]));
        self::assertSame(self::array($day, 'meals.1.entries.0.nutrients'), self::array($day, 'totals'));
        self::assertGreaterThan(400, self::number($day, 'totals.energyKcal'));
    }

    public function testAnEntryIsCorrectedMovedAndRemoved(): void
    {
        $this->register();
        $day = $this->logFood($this->foodId('riz blanc cru'), 100);
        $entry = self::string($day, 'meals.1.entries.0.id');
        $yesterday = new \DateTimeImmutable($this->today)->modify('-1 day')->format('Y-m-d');

        $moved = $this->call('PUT', '/api/nutrition/entries/'.$entry, ['day' => $yesterday, 'slot' => 'dinner', 'grams' => 200])->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame($yesterday, $moved['day']);
        self::assertSame(200.0, self::at($moved, 'meals.4.entries.0.grams'));
        self::assertSame(0.0, self::at($this->getJson('/api/nutrition/days/'.$this->today), 'totals.energyKcal'));

        $this->call('DELETE', '/api/nutrition/entries/'.$entry);
        self::assertResponseStatusCodeSame(204);
        $this->call('DELETE', '/api/nutrition/entries/'.$entry);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.journal_entry_not_found');
    }

    public function testEntriesArePrivate(): void
    {
        $this->register();
        $entry = self::string($this->logFood($this->foodId('riz blanc cru'), 100), 'meals.1.entries.0.id');

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');

        self::assertSame(0.0, self::at($this->getJson('/api/nutrition/days/'.$this->today), 'totals.energyKcal'));
        $this->call('PUT', '/api/nutrition/entries/'.$entry, ['day' => $this->today, 'slot' => 'dinner', 'grams' => 1]);
        self::assertResponseStatusCodeSame(404);
        $this->call('DELETE', '/api/nutrition/entries/'.$entry);
        self::assertResponseStatusCodeSame(404);
    }

    public function testImplausibleEntriesAreRejected(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');

        $this->call('POST', '/api/nutrition/entries', ['foodId' => 'nope', 'day' => '2026-02-30', 'slot' => 'brunch', 'grams' => 0]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'foodId'], ['propertyPath' => 'day'], ['propertyPath' => 'slot'], ['propertyPath' => 'grams']]]);

        $this->call('POST', '/api/nutrition/entries', ['foodId' => $rice, 'day' => new \DateTimeImmutable($this->today)->modify('+3 days')->format('Y-m-d'), 'slot' => 'lunch', 'grams' => 100]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_journal_day');

        $this->call('POST', '/api/nutrition/entries', ['foodId' => '00000000-0000-7000-8000-000000000000', 'day' => $this->today, 'slot' => 'lunch', 'grams' => 100]);
        self::assertResponseStatusCodeSame(404);

        $this->call('GET', '/api/nutrition/days/2026-13-01');
        self::assertResponseStatusCodeSame(422);
        $this->call('GET', '/api/nutrition/days/today');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAveragesCoverTheLoggedDays(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');
        $this->logFood($rice, 5000); // today: left out, still going on
        $this->logFood($rice, 100, 'lunch', new \DateTimeImmutable($this->today)->modify('-1 day')->format('Y-m-d'));
        $this->logFood($rice, 300, 'dinner', new \DateTimeImmutable($this->today)->modify('-3 days')->format('Y-m-d'));

        $averages = $this->call('GET', '/api/nutrition/averages?days=7&today='.$this->today)->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(7, $averages['days']);
        self::assertSame(2, $averages['loggedDays']);
        $kcal = self::number($this->getJson('/api/foods/'.$rice), 'per100g.energyKcal');
        self::assertEqualsWithDelta(2 * $kcal, self::number($averages, 'dailyAverage.energyKcal'), 0.1, '(100 g + 300 g) over 2 logged days');
        self::assertNull($averages['currentTarget']);

        $this->call('GET', '/api/nutrition/averages?days=365&today='.$this->today);
        self::assertResponseStatusCodeSame(422);
    }
}
