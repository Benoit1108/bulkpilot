<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Analysis\WeightFacts;
use App\Coach\Domain\Report\WeekFigures;
use App\Coach\Domain\Rule\CalorieRule;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Rule\ProteinRule;
use App\Coach\Domain\Rule\RuleEngine;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\Personality;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Coach\CoachTestCase;

/**
 * The exact edges of the Coach's rules: each threshold is on the side the rule says it is.
 */
final class BoundariesTest extends CoachTestCase
{
    public function testAWeekWithOnlyWeighInsHasAnAdherence(): void
    {
        $adherence = Adherence::of(null, new IntakeFacts(7, []), 2, new TrainingFacts(0, null, 0, []));

        self::assertNotNull($adherence->score());
        self::assertNull(Adherence::of(null, new IntakeFacts(7, []), 0, new TrainingFacts(0, null, 0, []))->score());
    }

    public function testTheMeanIsRoundedToTheNearestUnit(): void
    {
        $intake = new IntakeFacts(7, [['kcal' => 2201.2, 'protein' => 120.0], ['kcal' => 2200.0, 'protein' => 121.2]]);

        self::assertSame(2201.0, $intake->averageKcal(), '2 200,6');
        self::assertSame(121.0, $intake->averageProtein(), '120,6');
        self::assertSame(2200.0, new IntakeFacts(7, [['kcal' => 2200.4, 'protein' => 120.4], ['kcal' => 2200.0, 'protein' => 120.0]])->averageKcal(), '2 200,2');
    }

    public function testTheWeekChangeIsToATenthOfAKilo(): void
    {
        $figures = new WeekFigures(60.0, 60.37, null, null, null, null, 0, null, null, 0, null, 0);

        self::assertSame(0.4, $figures->changeKg());
    }

    public function testTenDaysLoggedAndEightWeighInsAreEnoughToJudge(): void
    {
        self::assertNotNull(new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(10, 2200)))));
        self::assertNotNull(new CalorieRule()->evaluate(self::facts(weight: new WeightFacts(60.0, 0.0, 3, 8))));
    }

    public function testExactlyEightyFivePercentIsToWatchNotPriority(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 1870))));

        self::assertSame(Kind::ReachTargetFirst, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
    }

    public function testExactlyOneHundredAndFivePercentStillGivesALowerTarget(): void
    {
        $finding = new CalorieRule()->evaluate(self::facts(weight: new WeightFacts(61.0, 1.0, 6, 24), intake: new IntakeFacts(14, self::eaten(14, 2310))));

        self::assertSame(Kind::DecreaseCalories, $finding?->kind);
    }

    public function testATargetAtTheFloorIsNotLowered(): void
    {
        $atTheFloor = new TargetFacts(new \DateTimeImmutable('2026-09-01'), CalorieRule::MIN_TARGET_KCAL, 80);

        self::assertNull(new CalorieRule()->evaluate(self::facts(target: $atTheFloor, weight: new WeightFacts(61.0, 1.0, 6, 24), intake: new IntakeFacts(14, self::eaten(14, 1000)))));
    }

    public function testTheShareIsReadInWholePercentRoundedDown(): void
    {
        self::assertSame(94, CalorieRule::percent(0.94999996));
        self::assertSame(95, CalorieRule::percent(0.949999996), 'a float error of the last digits is not a percent');
        self::assertSame(95, CalorieRule::percent(0.95));
    }

    public function testLevelsRankFromPriorityToInfo(): void
    {
        self::assertSame([0, 1, 2], [Level::Priority->rank(), Level::Watch->rank(), Level::Info->rank()]);
        self::assertLessThan(0, Kind::compare(Level::Watch, Kind::LowProtein, Level::Info, Kind::NoTarget), 'the level before the order of the kinds');
    }

    public function testFourDaysLoggedAreEnoughToJudgeProtein(): void
    {
        $finding = new ProteinRule()->evaluate(self::facts(week: new IntakeFacts(7, self::eaten(4, 2200, 60))));

        self::assertSame(Kind::LowProtein, $finding?->kind);
    }

    public function testTheEngineOrdersByLevelNotByRule(): void
    {
        // The calorie rule comes first but only informs (no pace); low protein is to watch.
        $findings = RuleEngine::findings(self::facts(pace: null, week: new IntakeFacts(7, self::eaten(7, 2200, 60))));

        self::assertSame([Kind::LowProtein, Kind::NoPace], array_map(static fn ($finding): Kind => $finding->kind, $findings));
    }

    public function testAPaceOfOneKiloIsAllowed(): void
    {
        $settings = CoachSettings::defaults(new UserId('0199a0b1-0000-7000-8000-000000000001'), new \DateTimeImmutable('2026-09-29'));
        $settings->change(Personality::Direct, -1.0, new \DateTimeImmutable('2026-09-29'));

        self::assertSame(-1.0, $settings->paceKgPerWeek());
    }
}
