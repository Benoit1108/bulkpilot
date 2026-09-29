<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application;

use App\Progression\Application\Goal\EndWeightGoal;
use App\Progression\Application\Goal\EndWeightGoalHandler;
use App\Progression\Application\Goal\GetWeightGoal;
use App\Progression\Application\Goal\GetWeightGoalHandler;
use App\Progression\Application\Goal\SetWeightGoal;
use App\Progression\Application\Goal\SetWeightGoalHandler;
use App\Progression\Application\Weight\GetWeighIn;
use App\Progression\Application\Weight\GetWeighInHandler;
use App\Progression\Application\Weight\GetWeightSummary;
use App\Progression\Application\Weight\GetWeightSummaryHandler;
use App\Progression\Application\Weight\ListWeighIns;
use App\Progression\Application\Weight\ListWeighInsHandler;
use App\Progression\Application\Weight\RemoveWeighIn;
use App\Progression\Application\Weight\RemoveWeighInHandler;
use App\Progression\Application\Weight\WeightReadings;
use App\Progression\Application\Weight\WeightSummaryView;
use App\Progression\Domain\Exception\InvalidWeightGoal;
use App\Progression\Domain\Exception\WeighInNotFound;
use App\Progression\Domain\Exception\WeightGoalNotFound;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\WeighInRecorded;
use App\Shared\Domain\Calendar\Period;
use App\Tests\Support\Progression\ProgressionTestCase;

final class WeightUseCasesTest extends ProgressionTestCase
{
    public function testWeighingAgainTheSameDayReplacesTheWeight(): void
    {
        $this->weigh(['2026-09-29' => 62.0]);
        $this->weigh(['2026-09-29' => 61.6]);

        self::assertCount(1, $this->weighIns->weighIns);
        self::assertSame(61.6, new GetWeighInHandler($this->weighIns)(new GetWeighIn($this->alex, self::day('2026-09-29')))->weightKg);
        self::assertSame([WeighInRecorded::class, WeighInRecorded::class], $this->events->publishedClasses());
    }

    public function testRemovingAWeighInTwiceChangesNothingAndOthersNeverSeeIt(): void
    {
        $this->weigh(['2026-09-28' => 62.0]);
        $this->weigh(['2026-09-28' => 80.0], $this->sam);
        $remove = new RemoveWeighInHandler($this->weighIns);

        $remove(new RemoveWeighIn($this->alex, self::day('2026-09-28')));
        $remove(new RemoveWeighIn($this->alex, self::day('2026-09-28')));

        self::assertSame(80.0, new GetWeighInHandler($this->weighIns)(new GetWeighIn($this->sam, self::day('2026-09-28')))->weightKg);
        $this->expectException(WeighInNotFound::class);
        new GetWeighInHandler($this->weighIns)(new GetWeighIn($this->alex, self::day('2026-09-28')));
    }

    public function testWeighInsComeTheMostRecentFirstByPages(): void
    {
        $this->weigh(['2026-09-25' => 61.0, '2026-09-27' => 61.5, '2026-09-29' => 62.0]);
        $list = new ListWeighInsHandler($this->weighIns);

        self::assertSame(['2026-09-29', '2026-09-27'], array_map(static fn ($view): string => $view->day->format('Y-m-d'), $list(new ListWeighIns($this->alex, 2, null))));
        self::assertSame(['2026-09-25'], array_map(static fn ($view): string => $view->day->format('Y-m-d'), $list(new ListWeighIns($this->alex, 500, self::day('2026-09-27')))));
    }

    public function testTheSummaryReadsTheAveragesTheSpeedAndTheChangeSinceTheStart(): void
    {
        $this->weigh(['2026-06-01' => 55.0, '2026-09-08' => 60.0, '2026-09-15' => 60.5, '2026-09-22' => 61.0, '2026-09-29' => 61.5]);
        $this->weigh(['2026-09-29' => 90.0], $this->sam);

        $summary = $this->summary(Period::Month);

        self::assertSame(61.5, $summary->latest?->kg);
        self::assertSame('2026-06-01', $summary->first?->day->format('Y-m-d'));
        self::assertSame(61.5, $summary->average7, 'the 23rd to the 29th: the 22nd is a week ago');
        self::assertSame(60.75, $summary->average30);
        self::assertSame(0.5, $summary->kgPerWeek);
        self::assertSame(TrendDirection::Gaining, $summary->direction);
        self::assertSame(6.5, $summary->sinceStartKg);
        self::assertCount(4, $summary->points, 'the last 30 days: from the 31st of August');
        self::assertSame(['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'], array_map(static fn ($week): string => $week->week->format('Y-m-d'), $summary->weeks));
        self::assertNull($summary->goal);
        self::assertCount(5, $this->summary(Period::All)->points);
    }

    public function testASummaryWithoutWeighInsIsEmpty(): void
    {
        $summary = $this->summary(Period::Quarter);

        self::assertNull($summary->latest);
        self::assertNull($summary->kgPerWeek);
        self::assertSame([], $summary->points);
    }

    public function testAGoalStartsFromTheLatestWeighInAndReplacesTheCurrentOne(): void
    {
        $this->weigh(['2026-09-01' => 48.0]);
        $this->setGoal(60.0, [55.0, 50.0]);
        $this->weigh(['2026-09-15' => 50.4, '2026-09-22' => 51.0, '2026-09-29' => 51.4]);
        $first = $this->goals->current($this->alex);

        $progress = new GetWeightGoalHandler($this->goals, new WeightReadings($this->weighIns))(new GetWeightGoal($this->alex, self::day('2026-09-29')));
        self::assertSame([48.0, '2026-09-01'], [$progress->goal->startKg(), $progress->goal->startDay()->format('Y-m-d')]);
        self::assertSame('2026-09-15', $progress->steps[0]->reachedOn?->format('Y-m-d'));
        self::assertSame(1.4, $progress->sinceLastStepKg);
        self::assertSame(28, $this->summary(Period::Month)->goal?->percent, 'the summary carries the goal');

        $this->setGoal(55.0, []);
        self::assertNotNull($first?->endedAt(), 'the previous goal is kept, ended');
        self::assertSame(51.4, $this->goals->current($this->alex)?->startKg());
    }

    public function testAGoalNeedsAWeighInAndEndingItTwiceChangesNothing(): void
    {
        try {
            $this->setGoal(60.0, []);
            self::fail('a goal without any weigh-in');
        } catch (InvalidWeightGoal $refused) {
            self::assertStringContainsString('weigh-in', $refused->getMessage());
        }

        $this->weigh(['2026-09-29' => 62.0]);
        $this->setGoal(58.0, []);
        $end = new EndWeightGoalHandler($this->goals, $this->clock);
        $end(new EndWeightGoal($this->alex));
        $end(new EndWeightGoal($this->alex));

        self::assertNull($this->goals->current($this->alex));
        $this->expectException(WeightGoalNotFound::class);
        new GetWeightGoalHandler($this->goals, new WeightReadings($this->weighIns))(new GetWeightGoal($this->alex, self::day('2026-09-29')));
    }

    /**
     * @param list<float> $milestones
     */
    private function setGoal(float $target, array $milestones): void
    {
        new SetWeightGoalHandler($this->goals, $this->weighIns, $this->clock)(new SetWeightGoal(new WeightGoalId($this->uuids->generate()), $this->alex, $target, $milestones));
    }

    private function summary(Period $period): WeightSummaryView
    {
        return new GetWeightSummaryHandler(new WeightReadings($this->weighIns), $this->weighIns, $this->goals)(new GetWeightSummary($this->alex, $period, self::day('2026-09-29')));
    }
}
