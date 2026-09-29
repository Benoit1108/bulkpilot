<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Analysis\WeightFacts;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\JournalRule;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Rule\ProteinRule;
use App\Coach\Domain\Rule\RecordsRule;
use App\Coach\Domain\Rule\RuleEngine;
use App\Coach\Domain\Rule\SessionsRule;
use App\Coach\Domain\Rule\StallRule;
use App\Coach\Domain\Rule\WeighInRule;
use App\Tests\Support\Coach\CoachTestCase;

final class RulesTest extends CoachTestCase
{
    public function testProteinClearlyShortOfTheTargetIsToWatch(): void
    {
        $finding = new ProteinRule()->evaluate(self::facts(week: new IntakeFacts(7, [...self::eaten(4, 2200, 100), ...self::eaten(2, 2200, 120)])));

        self::assertSame(Kind::LowProtein, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
        self::assertSame(['loggedDays' => 6, 'averageProtein' => 107.0, 'targetProtein' => 120, 'daysOnProtein' => 2], $finding->figures);
    }

    public function testProteinIsNotJudgedOnTooFewDaysNorAtNinetyPercent(): void
    {
        $rule = new ProteinRule();

        self::assertNull($rule->evaluate(self::facts(week: new IntakeFacts(7, self::eaten(3, 2200, 50)))), '3 days logged');
        self::assertNull($rule->evaluate(self::facts(week: new IntakeFacts(7, self::eaten(7, 2200, 108)))), '90 % of the target');
        self::assertNull($rule->evaluate(self::facts(target: new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 0), week: new IntakeFacts(7, self::eaten(7, 2200, 10)))), 'no protein target');
        self::assertNull($rule->evaluate(self::facts(target: null, week: new IntakeFacts(7, self::eaten(7, 2200, 10)))), 'no target');
    }

    public function testTooFewDaysLoggedIsSaid(): void
    {
        $finding = new JournalRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(9, 2200))));

        self::assertSame(Kind::IncompleteJournal, $finding?->kind);
        self::assertSame(Level::Info, $finding->level);
        self::assertSame(['days' => 14, 'loggedDays' => 9, 'neededDays' => 10], $finding->figures);
        self::assertNull(new JournalRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(10, 2200)))));
        self::assertNull(new JournalRule()->evaluate(self::facts(target: null, intake: new IntakeFacts(14, []))), 'the missing target comes first');
    }

    public function testFewerThanThreeWeighInsAWeekIsSaid(): void
    {
        $finding = new WeighInRule()->evaluate(self::facts(weight: new WeightFacts(60.0, null, 2, 5)));

        self::assertSame(Kind::FewWeighIns, $finding?->kind);
        self::assertSame(['weighIns7' => 2, 'weighIns28' => 5, 'neededPerWeek' => 3], $finding->figures);
        self::assertNull(new WeighInRule()->evaluate(self::facts(weight: new WeightFacts(60.0, 0.0, 3, 12))));
    }

    public function testSessionsMissedAgainstTheProgramAreToWatch(): void
    {
        $finding = new SessionsRule()->evaluate(self::facts(training: new TrainingFacts(2, 3, 0, [])));

        self::assertSame(Kind::MissedSessions, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
        self::assertSame(['days' => 7, 'sessions' => 2, 'planned' => 3], $finding->figures);
        self::assertNull(new SessionsRule()->evaluate(self::facts(training: new TrainingFacts(3, 3, 0, []))));
        self::assertNull(new SessionsRule()->evaluate(self::facts(training: new TrainingFacts(0, null, 0, []))), 'no program');
    }

    public function testStallsComeWithWhatWasEatenAndAtMostThreeExercises(): void
    {
        $finding = new StallRule()->evaluate(self::facts(intake: new IntakeFacts(14, self::eaten(14, 1760)), training: new TrainingFacts(3, 3, 0, self::stalls(4))));

        self::assertSame(Kind::Stall, $finding?->kind);
        self::assertSame(Level::Watch, $finding->level);
        self::assertSame([
            ['exercise' => 'Exercice 1', 'sessions' => 5, 'since' => '2026-08-01'],
            ['exercise' => 'Exercice 2', 'sessions' => 6, 'since' => '2026-08-01'],
            ['exercise' => 'Exercice 3', 'sessions' => 7, 'since' => '2026-08-01'],
        ], $finding->figures['exercises']);
        self::assertSame(80, $finding->figures['percentOfTarget']);
        self::assertSame(14, $finding->figures['loggedDays']);
    }

    public function testAStallWithoutATargetSaysNothingOfTheCalories(): void
    {
        $finding = new StallRule()->evaluate(self::facts(target: null, training: new TrainingFacts(3, 3, 0, self::stalls(1))));

        self::assertNull($finding?->figures['percentOfTarget']);
        self::assertNull(new StallRule()->evaluate(self::facts()), 'no stall');
    }

    public function testRecordsOfTheWeekAreAnInformation(): void
    {
        $finding = new RecordsRule()->evaluate(self::facts(training: new TrainingFacts(3, 3, 2, [])));

        self::assertSame(Kind::Records, $finding?->kind);
        self::assertSame(Level::Info, $finding->level);
        self::assertSame(['days' => 7, 'records' => 2, 'sessions' => 3], $finding->figures);
        self::assertNull(new RecordsRule()->evaluate(self::facts()));
    }

    public function testTheEngineOrdersByLevelThenEatingBeforeTraining(): void
    {
        $findings = RuleEngine::findings(self::facts(
            weight: new WeightFacts(60.0, 0.0, 2, 20),
            week: new IntakeFacts(7, self::eaten(7, 2200, 90)),
            training: new TrainingFacts(2, 3, 1, self::stalls(1)),
        ));

        self::assertSame(
            [Kind::IncreaseCalories, Kind::LowProtein, Kind::Stall, Kind::MissedSessions, Kind::FewWeighIns, Kind::Records],
            array_map(static fn (Finding $finding): Kind => $finding->kind, $findings),
        );
    }

    public function testNothingToSayWhenAllIsWell(): void
    {
        self::assertSame([], RuleEngine::findings(self::facts(weight: new WeightFacts(60.0, 0.5, 6, 24))));
    }

    public function testKindsAreOrderedByLevelThenByRule(): void
    {
        self::assertLessThan(0, Kind::compare(Level::Priority, Kind::Records, Level::Watch, Kind::IncreaseCalories), 'the level first');
        self::assertLessThan(0, Kind::compare(Level::Watch, Kind::LowProtein, Level::Watch, Kind::Stall), 'eating before training');
        self::assertLessThan(0, Kind::compare(Level::Watch, Kind::Stall, Level::Watch, Kind::MissedSessions));
        self::assertLessThan(0, Kind::compare(Level::Info, Kind::IncompleteJournal, Level::Info, Kind::FewWeighIns));
        self::assertGreaterThan(0, Kind::compare(Level::Info, Kind::Records, Level::Info, Kind::FewWeighIns));
        self::assertSame(0, Kind::compare(Level::Info, Kind::Records, Level::Info, Kind::Records));
        self::assertSame(0, Kind::NoTarget->rank());
    }
}
