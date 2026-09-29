<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Infrastructure;

use App\Coach\Application\Port\CoachAnalysis;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Settings\Personality;
use App\Coach\Infrastructure\Ai\FakeAiProvider;
use App\Coach\Infrastructure\Ai\French;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FakeAiProviderTest extends TestCase
{
    private const string NBSP = "\u{00A0}";
    private const string MINUS = "\u{2212}";

    /** Every figure any rule gives. */
    private const array FIGURES = [
        'days' => 14, 'loggedDays' => 12, 'neededDays' => 10, 'averageKcal' => 2340.0, 'targetKcal' => 2350, 'percentOfTarget' => 100,
        'kgPerWeek' => 0.0, 'paceKgPerWeek' => 0.5, 'average7Kg' => 48.2, 'averageProtein' => 95.0, 'targetProtein' => 120, 'daysOnProtein' => 2,
        'weighIns7' => 1, 'weighIns28' => 6, 'neededPerWeek' => 3, 'sessions' => 2, 'planned' => 3, 'records' => 1,
        'exercises' => [['exercise' => 'Squat', 'sessions' => 5, 'since' => '2026-08-01']],
    ];

    /**
     * @return iterable<string, array{Kind, Personality}>
     */
    public static function everyKindAndTone(): iterable
    {
        foreach (Kind::cases() as $kind) {
            foreach (Personality::cases() as $personality) {
                yield $kind->value.' '.$personality->value => [$kind, $personality];
            }
        }
    }

    private static function finding(Kind $kind): Finding
    {
        $proposal = match ($kind) {
            Kind::IncreaseCalories => new CalorieProposal(2350, 150),
            Kind::DecreaseCalories => new CalorieProposal(2350, -100),
            default => null,
        };

        return new Finding($kind, Level::Watch, self::FIGURES, $proposal);
    }

    private static function phrase(Finding $finding, Personality $personality = Personality::Neutral): \App\Coach\Domain\Recommendation\CoachMessage
    {
        return new FakeAiProvider()->generateRecommendation(new CoachAnalysis($finding, $personality));
    }

    #[DataProvider('everyKindAndTone')]
    public function testEveryKindIsPhrasedInEveryTone(Kind $kind, Personality $personality): void
    {
        $message = self::phrase(self::finding($kind), $personality);

        self::assertNotSame('', trim($message->title));
        self::assertNotSame('', trim($message->body));
        self::assertNotSame([], $message->why);
        foreach ($message->why as $line) {
            self::assertNotSame('', trim($line));
        }
        self::assertNotSame('', trim($message->action), 'what to do, for the weekly report');
        foreach ([$message->title, $message->body, $message->action, ...$message->why] as $text) {
            self::assertDoesNotMatchRegularExpression('/ [:;!?%]/u', $text, 'a non-breaking space before « : ; ! ? % »');
        }
        self::assertSame('fake', $message->provider);
    }

    public function testTheToneChangesTheBodyOnly(): void
    {
        $finding = self::finding(Kind::IncreaseCalories);
        $direct = self::phrase($finding, Personality::Direct);
        $encouraging = self::phrase($finding, Personality::Encouraging);

        self::assertSame($direct->title, $encouraging->title);
        self::assertSame($direct->why, $encouraging->why);
        self::assertSame($direct->action, $encouraging->action);
        self::assertNotSame($direct->body, $encouraging->body);
    }

    // Spec §69: the trend, what was eaten, the target, the share reached, then the proposal.
    public function testACalorieProposalIsExplainedWithItsFigures(): void
    {
        $message = self::phrase(self::finding(Kind::IncreaseCalories));

        self::assertSame('Proposition'.self::NBSP.': +150'.self::NBSP.'kcal', $message->title);
        self::assertSame([
            'Ton poids évolue de 0,0'.self::NBSP.'kg par semaine sur 4 semaines, pour +0,5'.self::NBSP.'kg visés.',
            'Calories moyennes'.self::NBSP.': 2'.self::NBSP.'340'.self::NBSP.'kcal (12 jours saisis sur 14).',
            'Objectif'.self::NBSP.': 2'.self::NBSP.'350'.self::NBSP.'kcal.',
            'Tu atteins donc environ 100'.self::NBSP.'% de ta cible.',
        ], $message->why);
        self::assertSame('Nouvel objectif proposé'.self::NBSP.': 2'.self::NBSP.'500'.self::NBSP.'kcal par jour, à partir de demain. Accepte-le ou refuse-le.', $message->body);
        self::assertSame('Passe à 2'.self::NBSP.'500'.self::NBSP.'kcal par jour (proposition à valider).', $message->action);
    }

    public function testALowerTargetIsWrittenWithATrueMinus(): void
    {
        $message = self::phrase(self::finding(Kind::DecreaseCalories));

        self::assertSame('Proposition'.self::NBSP.': '.self::MINUS.'100'.self::NBSP.'kcal', $message->title);
        self::assertStringContainsString('2'.self::NBSP.'250'.self::NBSP.'kcal', $message->body);
    }

    public function testAStallPointsAtEatingFirstWhenShortOfTheTarget(): void
    {
        $short = self::phrase(new Finding(Kind::Stall, Level::Watch, ['percentOfTarget' => 80] + self::FIGURES));
        $fed = self::phrase(new Finding(Kind::Stall, Level::Watch, self::FIGURES));
        $several = self::phrase(new Finding(Kind::Stall, Level::Watch, ['exercises' => [
            ['exercise' => 'Squat', 'sessions' => 5, 'since' => '2026-08-01'],
            ['exercise' => 'Développé couché', 'sessions' => 4, 'since' => '2026-08-12'],
        ], 'percentOfTarget' => null]));

        self::assertSame('Stagnation'.self::NBSP.': Squat', $short->title);
        self::assertSame('Squat'.self::NBSP.': pas de record depuis 5 séances (le 1 août).', $short->why[0]);
        self::assertSame('Atteins ta cible calorique avant de toucher au programme.', $short->action);
        self::assertSame('Soigne ton sommeil et ta récupération avant de toucher au programme.', $fed->action);
        self::assertStringContainsString('c’est la première piste', $short->why[1]);
        self::assertStringContainsString('l’alimentation n’explique pas', $fed->why[1]);
        self::assertSame('Stagnation sur 2 exercices', $several->title);
        self::assertCount(2, $several->why, 'no line on eating without a target');
    }

    public function testCountsAgreeWithTheirNumber(): void
    {
        $one = self::phrase(new Finding(Kind::Records, Level::Info, ['records' => 1, 'sessions' => 1]));
        $many = self::phrase(new Finding(Kind::Records, Level::Info, ['records' => 4, 'sessions' => 3]));

        self::assertSame('1 record cette semaine', $one->title);
        self::assertSame('1 record battu en 1 séance ces 7 derniers jours.', $one->why[0]);
        self::assertSame('4 records battus en 3 séances ces 7 derniers jours.', $many->why[0]);
    }

    public function testFiguresAreWrittenTheFrenchWay(): void
    {
        self::assertSame('2'.self::NBSP.'350', French::number(2350));
        self::assertSame('0,0', French::number(-0.04, 1), 'no minus on a rounded zero');
        self::assertSame(self::MINUS.'1'.self::NBSP.'234,5', French::number(-1234.5, 1));
        self::assertSame('+0,4'.self::NBSP.'kg', French::signedKg(0.44));
        self::assertSame(self::MINUS.'0,2'.self::NBSP.'kg', French::signedKg(-0.2));
        self::assertSame('0,0'.self::NBSP.'kg', French::signedKg(0.04));
        self::assertSame('+150'.self::NBSP.'kcal', French::signedKcal(150));
        self::assertSame('98'.self::NBSP.'g', French::grams(98));
        self::assertSame('48,2'.self::NBSP.'kg', French::kg(48.2));
        self::assertSame('99'.self::NBSP.'%', French::percent(99));
        self::assertSame('le 1 septembre', French::day('2026-09-01'));
        self::assertSame('le 25 décembre', French::day('2026-12-25'));
        self::assertSame('1 séance', French::count(1, 'séance', 'séances'));
        self::assertSame('0 séance', French::count(0, 'séance', 'séances'), 'in French, 0 takes the singular');
        self::assertSame('2 séances', French::count(2, 'séance', 'séances'));
        self::assertSame('+0,5'.self::NBSP.'kg', French::pace(0.5));
        self::assertSame('+0,25'.self::NBSP.'kg', French::pace(0.25), 'a pace set to the hundredth keeps it');
        self::assertSame(self::MINUS.'0,3'.self::NBSP.'kg', French::pace(-0.3));
        self::assertSame('Objectif'.self::NBSP.': oui'.self::NBSP.'! 90'.self::NBSP.'%', French::typeset('Objectif : oui ! 90 %'));
    }

    public function testSessionsAgreeWithWhatWasPlanned(): void
    {
        $one = self::phrase(new Finding(Kind::MissedSessions, Level::Watch, ['sessions' => 0, 'planned' => 1]));
        $many = self::phrase(new Finding(Kind::MissedSessions, Level::Watch, ['sessions' => 2, 'planned' => 3]));

        self::assertSame('0 séance sur 1 prévue ces 7 derniers jours.', $one->why[0]);
        self::assertSame('2 séances sur 3 prévues ces 7 derniers jours.', $many->why[0]);
    }

    /**
     * @return iterable<string, array{int, float, string}>
     */
    public static function situations(): iterable
    {
        yield 'a gain too slow' => [150, 0.5, 'Tu tiens bien ta cible'];
        yield 'a cut too fast' => [100, -0.5, 'Tu perds plus vite que prévu'];
        yield 'a maintenance going down' => [100, 0.0, 'Ton poids baisse un peu'];
        yield 'a gain too fast' => [-100, 0.5, 'un peu trop vite même'];
        yield 'a cut too slow' => [-150, -0.5, 'pour relancer la perte'];
        yield 'a maintenance going up' => [-100, 0.0, 'Ton poids monte un peu'];
    }

    #[DataProvider('situations')]
    public function testTheEncouragingToneFollowsTheSituation(int $by, float $pace, string $said): void
    {
        $kind = $by > 0 ? Kind::IncreaseCalories : Kind::DecreaseCalories;

        $message = self::phrase(new Finding($kind, Level::Priority, ['paceKgPerWeek' => $pace] + self::FIGURES, new CalorieProposal(2350, $by)), Personality::Encouraging);

        self::assertStringContainsString($said, $message->body);
    }

    public function testThePaceIsWrittenAsItWasSet(): void
    {
        $message = self::phrase(new Finding(Kind::ReachTargetFirst, Level::Watch, ['paceKgPerWeek' => 0.25] + self::FIGURES));

        self::assertStringContainsString('pour +0,25'.self::NBSP.'kg visés', $message->why[0]);
    }
}
