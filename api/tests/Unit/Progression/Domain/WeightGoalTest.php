<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Domain;

use App\Progression\Domain\Exception\InvalidWeightGoal;
use App\Progression\Domain\Goal\Estimate;
use App\Progression\Domain\Goal\EstimateStatus;
use App\Progression\Domain\Goal\GoalProgress;
use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\Weight;
use App\Tests\Support\Progression\ProgressionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class WeightGoalTest extends ProgressionTestCase
{
    /**
     * @param list<float> $milestones
     */
    private function goal(float $start, float $target, array $milestones = [], string $day = '2026-09-01'): WeightGoal
    {
        return WeightGoal::set(
            new WeightGoalId($this->uuids->generate()),
            $this->alex,
            new DayWeight(self::day($day), $start),
            new Weight($target),
            array_map(static fn (float $kg): Weight => new Weight($kg), $milestones),
            $this->clock->now(),
        );
    }

    public function testMilestonesAreSortedTheWayTheWeightGoes(): void
    {
        $gain = $this->goal(48.0, 60.0, [55.0, 50.0, 52.0]);
        self::assertSame(TrendDirection::Gaining, $gain->direction());
        self::assertSame([50.0, 52.0, 55.0, 60.0], $gain->steps());

        $cut = $this->goal(80.0, 72.0, [78.0, 75.0]);
        self::assertSame(TrendDirection::Losing, $cut->direction());
        self::assertSame([78.0, 75.0, 72.0], $cut->steps());
    }

    /**
     * @param list<float> $milestones
     */
    #[DataProvider('wrongGoals')]
    public function testAGoalIsAWayFromTheCurrentWeightWithMilestonesOnIt(float $target, array $milestones): void
    {
        $this->expectException(InvalidWeightGoal::class);

        $this->goal(60.0, $target, $milestones);
    }

    /**
     * @return iterable<string, array{float, list<float>}>
     */
    public static function wrongGoals(): iterable
    {
        yield 'too close' => [60.4, []];
        yield 'milestone past the target' => [65.0, [66.0]];
        yield 'milestone behind the start' => [65.0, [59.0]];
        yield 'the same milestone twice' => [65.0, [62.0, 62.0]];
        yield 'too many' => [80.0, [61.0, 62.0, 63.0, 64.0, 65.0, 66.0, 67.0, 68.0, 69.0, 70.0, 71.0]];
    }

    public function testProgressTellsTheStepsReachedTheShareDoneAndTheChangeSinceTheLastStep(): void
    {
        $goal = $this->goal(48.0, 60.0, [50.0, 52.0, 55.0]);
        $weighIns = self::series(['2026-08-20' => 51.0, '2026-09-05' => 49.0, '2026-09-12' => 50.2, '2026-09-19' => 51.5, '2026-09-26' => 52.4, '2026-09-29' => 51.8]);

        $progress = GoalProgress::of($goal, $weighIns, $weighIns[5], 0.42);

        self::assertSame(['2026-09-12', '2026-09-26', null, null], array_map(static fn ($step): ?string => $step->reachedOn?->format('Y-m-d'), $progress->steps), 'a weigh-in before the goal counts for nothing');
        self::assertTrue($progress->steps[3]->target);
        self::assertSame(32, $progress->percent);
        self::assertSame(-0.2, $progress->sinceLastStepKg, 'below the 52 kg reached');
        self::assertSame([55.0, 3.2], [$progress->next?->step->kg, $progress->next?->remainingKg]);
        self::assertSame(8.2, $progress->toTargetKg);
        self::assertSame([EstimateStatus::Estimated, 8], [$progress->next?->estimate->status, $progress->next?->estimate->weeks]);
        self::assertSame([EstimateStatus::Estimated, 20], [$progress->toTarget->status, $progress->toTarget->weeks]);
    }

    public function testACutReachesItsStepsGoingDown(): void
    {
        $goal = $this->goal(80.0, 72.0, [78.0, 75.0]);
        $weighIns = self::series(['2026-09-08' => 79.0, '2026-09-15' => 77.6, '2026-09-22' => 77.9, '2026-09-29' => 76.8]);

        $progress = GoalProgress::of($goal, $weighIns, $weighIns[3], -0.4);

        self::assertSame(['2026-09-15', null, null], array_map(static fn ($step): ?string => $step->reachedOn?->format('Y-m-d'), $progress->steps));
        self::assertSame(40, $progress->percent);
        self::assertSame(-1.2, $progress->sinceLastStepKg, 'down 1.2 kg since the 78 kg milestone');
        self::assertSame([75.0, -1.8, 5], [$progress->next?->step->kg, $progress->next?->remainingKg, $progress->next?->estimate->weeks]);
        self::assertSame(-4.8, $progress->toTargetKg);
    }

    public function testATargetReachedHasNothingLeft(): void
    {
        $goal = $this->goal(80.0, 75.0);
        $weighIns = self::series(['2026-09-20' => 76.0, '2026-09-29' => 74.8]);

        $progress = GoalProgress::of($goal, $weighIns, $weighIns[1], -0.5);

        self::assertSame(100, $progress->percent);
        self::assertNull($progress->next);
        self::assertSame(EstimateStatus::Reached, $progress->toTarget->status);
    }

    #[DataProvider('estimates')]
    public function testAnEstimateIsGivenOnlyWhenTheWeightMovesTheRightWay(float $remaining, ?float $speed, TrendDirection $direction, EstimateStatus $status, ?int $weeks): void
    {
        $estimate = Estimate::toward($remaining, $speed, $direction);

        self::assertSame([$status, $weeks], [$estimate->status, $estimate->weeks]);
    }

    /**
     * @return iterable<string, array{float, ?float, TrendDirection, EstimateStatus, ?int}>
     */
    public static function estimates(): iterable
    {
        yield '7 kg at +0.42 kg a week' => [7.0, 0.42, TrendDirection::Gaining, EstimateStatus::Estimated, 17];
        yield 'losing 3 kg at -0.5' => [-3.0, -0.5, TrendDirection::Losing, EstimateStatus::Estimated, 6];
        yield 'no speed yet' => [7.0, null, TrendDirection::Gaining, EstimateStatus::NotEnoughWeighIns, null];
        yield 'stable' => [7.0, 0.03, TrendDirection::Gaining, EstimateStatus::NotMoving, null];
        yield 'gaining, the weight going down' => [7.0, -0.2, TrendDirection::Gaining, EstimateStatus::MovingAway, null];
        yield 'losing, the weight going up' => [-3.0, 0.3, TrendDirection::Losing, EstimateStatus::MovingAway, null];
        yield 'gaining, past the target' => [-0.5, 0.4, TrendDirection::Gaining, EstimateStatus::Reached, null];
        yield 'losing, past the target' => [0.4, -0.4, TrendDirection::Losing, EstimateStatus::Reached, null];
        yield 'nothing left' => [0.0, 0.4, TrendDirection::Gaining, EstimateStatus::Reached, null];
    }

    public function testEndingAGoalKeepsTheFirstEnd(): void
    {
        $goal = $this->goal(60.0, 65.0);
        $goal->end(self::day('2026-09-10'));
        $goal->end(self::day('2026-09-20'));

        self::assertSame('2026-09-10', $goal->endedAt()?->format('Y-m-d'));
    }
}
