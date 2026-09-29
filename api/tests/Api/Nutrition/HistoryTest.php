<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class HistoryTest extends NutritionApiTestCase
{
    private function daysAgo(int $days): string
    {
        return new \DateTimeImmutable($this->today)->modify(\sprintf('-%d days', $days))->format('Y-m-d');
    }

    public function testTheHistoryGivesTheCompleteDaysLoggedAndTheirWeeks(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cuit');
        $this->logFood($rice, 200, day: $this->daysAgo(2));
        $this->logFood($rice, 100, day: $this->daysAgo(1));
        $this->logFood($rice, 100, day: $this->today);
        $this->logFood($rice, 100, day: $this->daysAgo(40));

        $history = $this->getJson('/api/nutrition/history?period=30d&today='.$this->today);

        self::assertSame([$this->daysAgo(2), $this->daysAgo(1)], array_column(self::array($history, 'days'), 'day'), 'today is under way, 40 days ago is out of the period');
        $first = self::number($history, 'days.0.energyKcal');
        $second = self::number($history, 'days.1.energyKcal');
        self::assertEqualsWithDelta(2 * $second, $first, 1.0);
        self::assertEqualsWithDelta(($first + $second) / 2, self::number($history, 'averageEnergyKcal'), 0.1);
        $logged = 0;
        foreach (array_keys(self::array($history, 'weeks')) as $week) {
            $logged += self::int($history, 'weeks.'.$week.'.days');
        }
        self::assertSame(2, $logged, 'both days, in one week or two');
        self::assertNull(self::at($history, 'target'));

        $this->register('sam@example.com', 'Sam');
        self::assertSame([], self::array($this->getJson('/api/nutrition/history'), 'days'));
        $this->call('GET', '/api/nutrition/history?today=someday');
        self::assertResponseStatusCodeSame(400);
    }
}
