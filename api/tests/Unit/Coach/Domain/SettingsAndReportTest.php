<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Domain;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\Pace;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Exception\InvalidCoachSettings;
use App\Coach\Domain\Report\WeekFacts;
use App\Coach\Domain\Report\WeekFigures;
use App\Coach\Domain\Report\WeeklyReport;
use App\Coach\Domain\Report\WeeklyReportId;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\Personality;
use App\Tests\Support\Coach\CoachTestCase;
use PHPUnit\Framework\Attributes\TestWith;

final class SettingsAndReportTest extends CoachTestCase
{
    public function testTheCoachStartsDirectFollowingTheNutritionPace(): void
    {
        $settings = CoachSettings::defaults($this->alex, $this->clock->now());

        self::assertSame(Personality::Direct, $settings->personality());
        self::assertNull($settings->paceKgPerWeek());
        self::assertNull($settings->lastAnalysedOn());
        self::assertNull($settings->adherence());
        self::assertFalse($settings->hasAnalysed(self::day(self::TODAY)));
    }

    public function testTheToneAndPaceChange(): void
    {
        $settings = CoachSettings::defaults($this->alex, $this->clock->now());
        $this->clock->modify('+1 hour');

        $settings->change(Personality::Encouraging, -0.25, $this->clock->now());

        self::assertSame(Personality::Encouraging, $settings->personality());
        self::assertSame(-0.25, $settings->paceKgPerWeek());
        self::assertSame('2026-09-29 09:00:00', $settings->updatedAt()->format('Y-m-d H:i:s'));
    }

    #[TestWith([1.01])]
    #[TestWith([-1.5])]
    #[TestWith([0.555])]
    public function testAPaceIsAtMostOneKiloAWeekToTheHundredth(float $pace): void
    {
        $this->expectException(InvalidCoachSettings::class);

        CoachSettings::defaults($this->alex, $this->clock->now())->change(Personality::Neutral, $pace, $this->clock->now());
    }

    public function testTheLastAnalysisDayAndItsAdherenceAreKept(): void
    {
        $settings = CoachSettings::defaults($this->alex, $this->clock->now());
        $adherence = Adherence::of(null, new IntakeFacts(7, self::eaten(2, 2000)), 2, new TrainingFacts(0, null, 0, []));

        $settings->markAnalysed(new \DateTimeImmutable('2026-09-29 21:30'), $adherence);

        self::assertTrue($settings->hasAnalysed(self::day('2026-09-29')));
        self::assertTrue($settings->hasAnalysed(self::day('2026-09-28')), 'never back to an earlier day');
        self::assertFalse($settings->hasAnalysed(self::day('2026-09-30')));
        self::assertSame('2026-09-29 00:00', $settings->lastAnalysedOn()?->format('Y-m-d H:i'));
        self::assertSame($adherence->toArray(), $settings->adherence()?->toArray());
        self::assertSame(67, $settings->adherence()->score());
    }

    private static function figures(?float $startKg = 59.6, ?float $endKg = 60.0, ?float $kgPerWeek = 0.4, int $loggedDays = 7, int $sessions = 3): WeekFigures
    {
        return new WeekFigures($startKg, $endKg, $kgPerWeek, 0.5, 2310.0, 98.0, $loggedDays, 2350, 120, $sessions, 3, 4);
    }

    public function testAReportKeepsTheWeekItsVerdictAndItsAction(): void
    {
        $adherence = Adherence::of(new TargetFacts(self::day('2026-09-01'), 2350, 120), new IntakeFacts(7, self::eaten(7, 2310, 98)), 5, new TrainingFacts(3, 3, 4, []));

        $report = WeeklyReport::draw(new WeeklyReportId($this->uuids->generate()), $this->alex, new \DateTimeImmutable('2026-09-21 12:00'), new WeekFacts(self::figures(), $adherence), null, $this->clock->now());

        self::assertSame('2026-09-21 00:00', $report->weekStart()->format('Y-m-d H:i'));
        self::assertSame(self::figures()->toArray(), $report->figures()->toArray());
        self::assertSame(0.4, $report->figures()->changeKg());
        self::assertSame(Pace::OnTrack, $report->verdict());
        self::assertNull($report->action());
        self::assertSame($adherence->score(), $report->adherence()->score());
        self::assertSame([['calories', 7, 7], ['protein', 0, 7], ['training', 3, 3], ['weigh_ins', 5, 3]], AdherenceTest::parts($report->adherence()->components));
    }

    public function testTheVerdictIsTheSpeedAgainstThePace(): void
    {
        $adherence = Adherence::of(null, new IntakeFacts(7, []), 0, new TrainingFacts(0, null, 0, []));
        $draw = fn (WeekFigures $figures): Pace => WeeklyReport::draw(new WeeklyReportId($this->uuids->generate()), $this->alex, self::day('2026-09-21'), new WeekFacts($figures, $adherence), 'Passe à 2 500 kcal par jour.', $this->clock->now())->verdict();

        self::assertSame(Pace::Below, $draw(self::figures(kgPerWeek: 0.0)));
        self::assertSame(Pace::Above, $draw(self::figures(kgPerWeek: 1.0)));
        self::assertSame(Pace::Unknown, $draw(self::figures(kgPerWeek: null)));
    }

    public function testAWeekWithNothingLoggedWeighedOrTrainedIsEmpty(): void
    {
        self::assertTrue(self::figures(startKg: null, endKg: null, loggedDays: 0, sessions: 0)->isEmpty());
        self::assertFalse(self::figures(startKg: null, endKg: null, loggedDays: 1, sessions: 0)->isEmpty());
        self::assertFalse(self::figures(startKg: null, endKg: null, loggedDays: 0, sessions: 1)->isEmpty());
        self::assertFalse(self::figures(startKg: null, loggedDays: 0, sessions: 0)->isEmpty());
        self::assertNull(self::figures(startKg: null)->changeKg());
    }

    public function testFiguresSurviveTheirStorage(): void
    {
        $figures = self::figures();

        $back = WeekFigures::fromArray($figures->toArray());

        self::assertSame($figures->toArray(), $back->toArray());
        self::assertSame(0.4, $back->changeKg());
    }
}
