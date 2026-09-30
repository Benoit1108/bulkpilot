<?php

declare(strict_types=1);

namespace App\Tests\Api\Progression;

use App\Tests\Support\Api\ProgressionApiTestCase;

final class WeightTest extends ProgressionApiTestCase
{
    public function testADayHasOneWeighInThatWeighingAgainReplaces(): void
    {
        $this->register();
        $day = $this->daysAgo(1);

        $this->weigh($day, 62.04);
        self::assertJsonContains(['day' => $day, 'weightKg' => 62.0]);
        $this->weigh($day, 61.8);

        $weighIns = $this->getJson('/api/progression/weigh-ins');
        self::assertCount(1, $weighIns);
        self::assertSame([$day, 61.8], [self::string($weighIns, '0.day'), self::number($weighIns, '0.weightKg')]);
        self::assertSame(61.8, self::number($this->getJson('/api/progression/weigh-ins/'.$day), 'weightKg'));

        $this->call('DELETE', '/api/progression/weigh-ins/'.$day);
        self::assertResponseStatusCodeSame(204);
        $this->call('DELETE', '/api/progression/weigh-ins/'.$day);
        self::assertResponseStatusCodeSame(204, 'removing it again changes nothing');
        $this->call('GET', '/api/progression/weigh-ins/'.$day);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('progression.weigh_in_not_found');
    }

    public function testAWeighInIsAPlausibleWeightOnADayThatHasCome(): void
    {
        $this->register();

        $this->call('PUT', '/api/progression/weigh-ins/'.$this->daysAgo(0), ['weightKg' => 12]);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/progression/weigh-ins/'.$this->today->modify('+2 days')->format('Y-m-d'), ['weightKg' => 62]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('progression.invalid_weigh_in');
        $this->call('PUT', '/api/progression/weigh-ins/2026-02-30', ['weightKg' => 62]);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/progression/weigh-ins/yesterday', ['weightKg' => 62]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheWeighInsAreTheUsersOwn(): void
    {
        $this->register();
        $this->weigh($this->daysAgo(0), 62.0);

        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->getJson('/api/progression/weigh-ins'));
        $this->call('GET', '/api/progression/weigh-ins/'.$this->daysAgo(0));
        self::assertResponseStatusCodeSame(404);
        self::assertNull(self::at($this->getJson('/api/progression/weight'), 'latest'));
        $this->call('GET', '/api/progression/weight-goal');
        self::assertResponseStatusCodeSame(404);
    }

    public function testWeighInsComeByPages(): void
    {
        $this->register();
        foreach ([3, 2, 1] as $ago) {
            $this->weigh($this->daysAgo($ago), 60.0 + $ago);
        }

        $page = $this->getJson('/api/progression/weigh-ins?limit=2');
        self::assertSame([$this->daysAgo(1), $this->daysAgo(2)], array_column($page, 'day'));
        self::assertSame([$this->daysAgo(3)], array_column($this->getJson('/api/progression/weigh-ins?limit=2&before='.$this->daysAgo(2)), 'day'));
        $this->call('GET', '/api/progression/weigh-ins?before=soon');
        self::assertResponseStatusCodeSame(400);
    }

    public function testTheSummaryGivesTheAveragesTheSpeedAndTheChartOfThePeriod(): void
    {
        $this->register();
        foreach ([21 => 60.0, 14 => 60.5, 7 => 61.0, 0 => 61.5] as $ago => $kg) {
            $this->weigh($this->daysAgo($ago), $kg);
        }

        $summary = $this->getJson('/api/progression/weight?period=30d&today='.$this->daysAgo(0));

        self::assertSame(['day' => $this->daysAgo(0), 'weightKg' => 61.5], self::array($summary, 'latest'));
        self::assertSame(61.5, self::number($summary, 'average7Kg'));
        self::assertSame(60.75, self::number($summary, 'average30Kg'));
        self::assertSame(0.5, self::number($summary, 'kgPerWeek'));
        self::assertSame('gaining', $summary['direction']);
        self::assertSame(1.5, self::number($summary, 'sinceStartKg'));
        self::assertSame(1.5, self::number($summary, 'periodChangeKg'), 'over the 30 days');
        self::assertCount(4, self::array($summary, 'points'));
        self::assertSame(['day', 'weightKg', 'average7Kg', 'average30Kg'], array_keys(self::array($summary, 'points.0')));
        self::assertSame([['2026-09-07', 60.0], ['2026-09-14', 60.5], ['2026-09-21', 61.0], ['2026-09-28', 61.5]], array_map(static fn (mixed $week): array => [self::string($week, 'week'), self::number($week, 'averageKg')], self::array($summary, 'weeks')), 'a week is its Monday');
        $week = $this->getJson('/api/progression/weight?period=7d');
        self::assertCount(1, self::array($week, 'points'));
        self::assertNull($week['periodChangeKg'], 'a single weigh-in over 7 days: no change to tell');

        $this->call('GET', '/api/progression/weight?period=2w');
        self::assertResponseStatusCodeSame(422, 'not one of the periods');
        $this->call('GET', '/api/progression/weight?today='.$this->today->modify('-3 days')->format('Y-m-d'));
        self::assertResponseStatusCodeSame(400);
    }

    public function testAGoalWithMilestonesStartsFromTheLatestWeighIn(): void
    {
        $this->register();
        $this->call('PUT', '/api/progression/weight-goal', ['targetKg' => 60, 'milestonesKg' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('progression.invalid_weight_goal');

        $this->weigh($this->daysAgo(0), 48.0);
        $goal = $this->call('PUT', '/api/progression/weight-goal', ['targetKg' => 60, 'milestonesKg' => [55, 50, 52.5]])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame([48.0, 'gaining', 0], [$goal['startWeightKg'], $goal['direction'], $goal['percent']]);
        self::assertSame([50.0, 52.5, 55.0, 60.0], array_column(self::array($goal, 'steps'), 'weightKg'));
        self::assertSame([false, false, false, true], array_column(self::array($goal, 'steps'), 'target'));
        self::assertSame(['status' => 'not_enough_weigh_ins', 'weeks' => null], self::array($goal, 'toTarget'));
        self::assertSame(12.0, self::number($goal, 'toTargetKg'));

        $this->weigh($this->daysAgo(0), 50.2);
        $goal = $this->getJson('/api/progression/weight-goal');
        self::assertSame($this->daysAgo(0), self::string($goal, 'steps.0.reachedOn'));
        self::assertSame([52.5, 2.3], [self::number($goal, 'next.weightKg'), self::number($goal, 'next.remainingKg')]);
        self::assertSame(self::string($goal, 'id'), self::string($this->getJson('/api/progression/weight'), 'goal.id'));

        $this->call('PUT', '/api/progression/weight-goal', ['targetKg' => 70, 'milestonesKg' => [45]]);
        self::assertResponseStatusCodeSame(422, 'a milestone behind the start');
        $replaced = $this->call('PUT', '/api/progression/weight-goal', ['targetKg' => 55])->toArray();
        self::assertResponseStatusCodeSame(200, 'a new goal replaces the one followed');
        self::assertSame([50.2, 55.0], [$replaced['startWeightKg'], $replaced['targetKg']]);
        self::assertNotSame(self::string($goal, 'id'), self::string($replaced, 'id'));

        $this->call('DELETE', '/api/progression/weight-goal');
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/progression/weight-goal');
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('progression.weight_goal_not_found');
    }
}
