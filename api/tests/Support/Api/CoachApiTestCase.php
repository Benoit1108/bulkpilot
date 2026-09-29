<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

/**
 * A signed-in user's Coach on the fixed Wednesday of the progression tests, with helpers to give
 * it weeks of eating and weighing to judge.
 */
abstract class CoachApiTestCase extends ProgressionApiTestCase
{
    /** 2 500 kcal a day, 150 g of protein: the target of the spec §66 flow. */
    protected const array TARGET = ['energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75];

    /**
     * Sets a target that started days ago: the clock goes back to that day, then returns.
     *
     * @param array<string, int> $target
     */
    protected function targetSince(int $daysAgo, array $target = self::TARGET): void
    {
        self::mockTime($this->daysAgo($daysAgo).' 10:00:00');
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => $this->daysAgo($daysAgo)] + $target);
        self::assertResponseStatusCodeSame(201);
        self::mockTime(self::TODAY.' 10:00:00');
    }

    /** A meal of 250 kcal and 15 g of protein per 100 g: 1 000 g is exactly the target. */
    protected function meal(): string
    {
        $food = $this->call('POST', '/api/foods', ['name' => 'Repas type', 'energyKcal' => 250, 'protein' => 15, 'carbohydrate' => 25, 'fat' => 10])->toArray();
        self::assertResponseStatusCodeSame(201);

        return self::string($food, 'id');
    }

    /**
     * Eats the same every day, from yesterday back.
     */
    protected function eatDays(int $days, float $grams): void
    {
        $meal = $this->meal();
        for ($ago = 1; $ago <= $days; ++$ago) {
            $this->call('POST', '/api/nutrition/entries', ['foodId' => $meal, 'day' => $this->daysAgo($ago), 'slot' => 'lunch', 'grams' => $grams]);
            self::assertResponseStatusCodeSame(201);
        }
    }

    /** A weigh-in every other day over the last 4 weeks, always the same weight: a stable trend. */
    protected function stableWeight(float $kg = 60.0): void
    {
        for ($ago = 0; $ago <= 26; $ago += 2) {
            $this->weigh($this->daysAgo($ago), $kg);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed> the Coach after the analysis
     */
    protected function analyse(bool $again = false, array $body = []): array
    {
        $coach = $this->call('POST', '/api/coach/analysis', $body + ['today' => self::TODAY, 'again' => $again])->toArray();
        self::assertResponseStatusCodeSame(200);

        return $coach;
    }

    /**
     * @param array<mixed> $coach
     *
     * @return list<string>
     */
    protected static function kinds(array $coach): array
    {
        return array_values(array_map(static fn (mixed $one): string => self::string($one, 'kind'), self::array($coach, 'recommendations')));
    }

    /**
     * @param array<mixed> $coach
     *
     * @return array<mixed>
     */
    protected static function recommendation(array $coach, string $kind): array
    {
        foreach (self::array($coach, 'recommendations') as $recommendation) {
            if ($kind === self::at($recommendation, 'kind')) {
                return self::array($recommendation);
            }
        }
        self::fail(\sprintf('No %s recommendation among %s.', $kind, implode(', ', self::kinds($coach))));
    }

    /** The Coach writes non-breaking spaces before units: compared as plain ones. */
    protected static function spaced(string $text): string
    {
        return str_replace("\u{00A0}", ' ', $text);
    }

    /**
     * @return array<mixed> the recommendation after the decision
     */
    protected function decide(string $id, string $decision): array
    {
        return $this->call('POST', '/api/coach/recommendations/'.$id.'/decision', ['decision' => $decision, 'today' => self::TODAY])->toArray(false);
    }
}
