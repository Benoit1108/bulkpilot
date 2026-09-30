<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application;

use App\Progression\Application\Notifying\WeighInDay;
use App\Progression\Application\Weight\WeightReadings;
use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\Weight;
use App\Shared\Application\Notifying\ReachedStep;
use App\Tests\Support\Progression\ProgressionTestCase;

final class NotifyingTest extends ProgressionTestCase
{
    private function weighing(string $day = '2026-09-29'): WeighInDay
    {
        return new WeighInDay(new WeightReadings($this->weighIns), $this->goals);
    }

    public function testTheDayTellsTodaysWeighInAndTheHabitOfTheWeekBefore(): void
    {
        $this->weigh(['2026-09-21' => 60.0, '2026-09-22' => 60.1, '2026-09-25' => 60.2, '2026-09-28' => 60.3, '2026-09-29' => 60.4, '2026-09-30' => 60.5]);

        $day = $this->weighing()->day($this->alex, self::day('2026-09-29'));

        self::assertTrue($day->weighedToday);
        self::assertSame(3, $day->weighInsLastWeek, 'the 22nd, 25th and 28th; the 21st is 8 days before');
        self::assertSame([], $day->reached, 'no goal');
        self::assertFalse($this->weighing()->day($this->sam, self::day('2026-09-29'))->weighedToday);
    }

    public function testTheStepsOfTheGoalReachedAreRead(): void
    {
        $this->weigh(['2026-09-01' => 48.0]);
        $this->goals->save(WeightGoal::set(new WeightGoalId($this->uuids->generate()), $this->alex, new DayWeight(self::day('2026-09-01'), 48.0), new Weight(52.0), [new Weight(50.0)], self::day('2026-09-01')));
        $this->weigh(['2026-09-20' => 50.2, '2026-09-29' => 51.0]);

        $reached = $this->weighing()->day($this->alex, self::day('2026-09-29'))->reached;

        self::assertSame([[50.0, false, '2026-09-20']], array_map(static fn (ReachedStep $step): array => [$step->weightKg, $step->target, $step->reachedOn->format('Y-m-d')], $reached));
        self::assertSame($this->goals->current($this->alex)?->id()->value, $reached[0]->goalId);
    }
}
