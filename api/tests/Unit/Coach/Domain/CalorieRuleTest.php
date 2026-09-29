<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\Pace;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\WeightFacts;
use App\Coach\Domain\Rule\CalorieRule;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Tests\Support\Coach\CoachTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CalorieRuleTest extends CoachTestCase
{
    /**
     * @return iterable<string, array{?float, ?float, Pace}>
     */
    public static function paces(): iterable
    {
        // Gaining 0.5 kg a week: the band is 0.25 kg either side.
        yield 'gain, too slow' => [0.5, 0.24, Pace::Below];
        yield 'gain, lower edge' => [0.5, 0.25, Pace::OnTrack];
        yield 'gain, on pace' => [0.5, 0.5, Pace::OnTrack];
        yield 'gain, upper edge' => [0.5, 0.75, Pace::OnTrack];
        yield 'gain, too fast' => [0.5, 0.76, Pace::Above];
        // Losing 0.5 kg a week: losing more is below, losing less is above.
        yield 'cut, too fast' => [-0.5, -0.8, Pace::Below];
        yield 'cut, on pace' => [-0.5, -0.4, Pace::OnTrack];
        yield 'cut, too slow' => [-0.5, -0.2, Pace::Above];
        // Maintaining: the band is at least 0.2 kg.
        yield 'maintain, stable' => [0.0, 0.15, Pace::OnTrack];
        yield 'maintain, drifting up' => [0.0, 0.21, Pace::Above];
        yield 'maintain, drifting down' => [0.0, -0.21, Pace::Below];
        yield 'no pace' => [null, 0.3, Pace::Unknown];
        yield 'no trend' => [0.5, null, Pace::Unknown];
    }

    #[DataProvider('paces')]
    public function testTheWeightIsJudgedAgainstABandAroundThePace(?float $aimed, ?float $speed, Pace $expected): void
    {
        self::assertSame($expected, Pace::of($aimed, $speed));
    }

    public function testWithoutATargetTheCoachAsksForOne(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(target: null));

        self::assertSame(Kind::NoTarget, $finding?->kind);
        self::assertSame(Level::Info, $finding->level);
    }

    public function testWithoutAPaceTheCoachAsksForOne(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(pace: null));

        self::assertSame(Kind::NoPace, $finding?->kind);
        self::assertSame(['targetKcal' => 2200], $finding->figures);
    }

    // Spec §66: target 2 200, eaten ~2 200, weight stable → +150 kcal.
    public function testAStableWeightOnTheTargetGivesAProposalOf150MoreForAGain(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts());

        self::assertSame(Kind::IncreaseCalories, $finding?->kind);
        self::assertSame(Level::Priority, $finding->level);
        self::assertSame(2200, $finding->proposal?->fromKcal);
        self::assertSame(150, $finding->proposal->byKcal);
        self::assertSame(2350, $finding->proposal->toKcal());
        self::assertSame([
            'days' => 14,
            'loggedDays' => 14,
            'averageKcal' => 2200.0,
            'targetKcal' => 2200,
            'percentOfTarget' => 100,
            'kgPerWeek' => 0.0,
            'paceKgPerWeek' => 0.5,
            'average7Kg' => 60.0,
        ], $finding->figures);
    }

    // Spec §66: target 2 200, eaten 1 850, weight stable → reach the target first.
    public function testEatingFarBelowTheTargetAsksToReachItFirst(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 1850))));

        self::assertSame(Kind::ReachTargetFirst, $finding?->kind);
        self::assertSame(Level::Priority, $finding->level, 'under 85 % of the target');
        self::assertNull($finding->proposal);
        self::assertSame(84, $finding->figures['percentOfTarget']);
        self::assertSame(1850.0, $finding->figures['averageKcal']);
    }

    public function testEatingSomewhatBelowTheTargetIsToWatch(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 1980))));

        self::assertSame(Kind::ReachTargetFirst, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
    }

    public function testFromNinetyFivePercentTheTargetCountsAsReached(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 2090))));

        self::assertSame(Kind::IncreaseCalories, $finding?->kind);
    }

    public function testGainingTooFastGivesAProposalOf100Less(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(weight: new WeightFacts(61.0, 1.0, 6, 24)));

        self::assertSame(Kind::DecreaseCalories, $finding?->kind);
        self::assertSame(-100, $finding->proposal?->byKcal);
        self::assertSame(2100, $finding->proposal->toKcal());
    }

    public function testGainingTooFastAboveTheTargetAsksToKeepToItFirst(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(weight: new WeightFacts(61.0, 1.0, 6, 24), intake: new IntakeFacts(14, self::eaten(14, 2420))));

        self::assertSame(Kind::BackToTarget, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
        self::assertNull($finding->proposal);
    }

    public function testACutLosingTooSlowlyGivesAProposalOf150Less(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(pace: -0.5, weight: new WeightFacts(60.0, -0.1, 6, 24)));

        self::assertSame(Kind::DecreaseCalories, $finding?->kind);
        self::assertSame(-150, $finding->proposal?->byKcal);
    }

    public function testACutLosingTooFastGivesAProposalOf100More(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(pace: -0.5, weight: new WeightFacts(60.0, -1.0, 6, 24)));

        self::assertSame(Kind::IncreaseCalories, $finding?->kind);
        self::assertSame(100, $finding->proposal?->byKcal);
    }

    public function testAMaintenanceDriftingIsNudgedBy100(): void
    {
        $down = new CalorieRule()->evaluate(self::facts(pace: 0.0, weight: new WeightFacts(60.0, -0.3, 6, 24)));
        $up = new CalorieRule()->evaluate(self::facts(pace: 0.0, weight: new WeightFacts(60.0, 0.3, 6, 24)));

        self::assertSame(100, $down?->proposal?->byKcal);
        self::assertSame(-100, $up?->proposal?->byKcal);
    }

    public function testOnPaceNothingIsProposed(): void
    {
        self::assertNull(new CalorieRule()->evaluate(self::facts(weight: new WeightFacts(60.0, 0.5, 6, 24))));
    }

    /**
     * @return iterable<string, array{Facts}>
     */
    public static function notEnoughToJudge(): iterable
    {
        yield 'a target set 9 days ago' => [self::facts(target: new TargetFacts(new \DateTimeImmutable('2026-09-20'), 2200, 120))];
        yield '9 days logged of 14' => [self::facts(intake: new IntakeFacts(14, self::eaten(9, 2200)))];
        yield '7 weigh-ins in 4 weeks' => [self::facts(weight: new WeightFacts(60.0, 0.0, 3, 7))];
        yield 'no trend' => [self::facts(weight: new WeightFacts(60.0, null, 3, 8))];
        yield 'nothing eaten logged' => [self::facts(intake: new IntakeFacts(14, []))];
    }

    #[DataProvider('notEnoughToJudge')]
    public function testTheCaloriesAreNotJudgedWithoutEnoughData(Facts $facts): void
    {
        self::assertNull(new CalorieRule()->evaluate($facts));
    }

    public function testATargetInForceForTwoWeeksIsJudged(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(target: new TargetFacts(new \DateTimeImmutable('2026-09-15'), 2200, 120)));

        self::assertSame(Kind::IncreaseCalories, $finding?->kind);
    }

    public function testTheShareEatenIsRoundedDownNeverAboveWhatWasReached(): void
    {
        // 2 089 of 2 200: 94.95 %, short of the 95 % that counts as reached.
        $finding = new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 2089))));

        self::assertSame(Kind::ReachTargetFirst, $finding?->kind);
        self::assertSame(94, $finding->figures['percentOfTarget']);
        self::assertSame(94, CalorieRule::percent(0.9496));
        self::assertSame(100, CalorieRule::percent(1.0));
    }

    public function testAProposalNeverLeavesTheBoundsOfATarget(): void
    {
        $atTheTop = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 6900, 200);
        $atTheCeiling = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 7000, 200);
        $nearTheFloor = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 1080, 60);
        $gaining = new WeightFacts(60.0, 1.2, 5, 20);

        $up = new CalorieRule()->evaluate(self::facts(target: $atTheTop, intake: new IntakeFacts(14, self::eaten(14, 6900))));
        $none = new CalorieRule()->evaluate(self::facts(target: $atTheCeiling, intake: new IntakeFacts(14, self::eaten(14, 7000))));
        $down = new CalorieRule()->evaluate(self::facts(target: $nearTheFloor, weight: $gaining, intake: new IntakeFacts(14, self::eaten(14, 1080))));

        self::assertSame(100, $up?->proposal?->byKcal, 'up to 7 000 kcal');
        self::assertNull($none, 'nothing above 7 000 kcal');
        self::assertSame(-80, $down?->proposal?->byKcal, 'down to 1 000 kcal');
    }
}
