<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Application;

use App\Coach\Application\Analysis\AnalyseCoach;
use App\Coach\Application\Analysis\AnalyseCoachHandler;
use App\Coach\Application\Analysis\GetCoach;
use App\Coach\Application\Analysis\GetCoachHandler;
use App\Coach\Application\Port\AiProvider;
use App\Coach\Application\Port\CoachAnalysis;
use App\Coach\Application\Recommendation\RecommendationView;
use App\Coach\Domain\Analysis\Pace;
use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationMade;
use App\Coach\Domain\Recommendation\Status;
use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\Personality;
use App\Coach\Infrastructure\Ai\FakeAiProvider;
use App\Shared\Application\Coaching\ExerciseStall;
use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\WeightReading;
use App\Shared\Domain\Event\DomainEvent;
use App\Tests\Support\Coach\CoachTestCase;
use App\Tests\Support\Coach\InMemoryRecommendationRepository;

final class AnalyseCoachTest extends CoachTestCase
{
    /**
     * @return list<string> the kinds open, most recent first
     */
    private function openKinds(): array
    {
        return array_map(static fn (Recommendation $recommendation): string => $recommendation->kind()->value, $this->recommendations->openOf($this->alex));
    }

    /**
     * @return list<string> the kinds shown, as the Coach page orders them
     */
    private function shownKinds(): array
    {
        $coach = new GetCoachHandler($this->settings, $this->recommendations, $this->reports, $this->clock);

        return array_map(static fn (RecommendationView $view): string => $view->kind->value, $coach(new GetCoach($this->alex))->recommendations);
    }

    private function phrased(): int
    {
        return \count(array_filter($this->events->published, static fn (DomainEvent $event): bool => $event instanceof RecommendationMade));
    }

    public function testAStalledGainOnTargetGetsAProposalPhrasedAndAnnounced(): void
    {
        $this->stalledGain();

        $this->analyse();

        $proposals = $this->recommendations->ofKind('calories.increase');
        self::assertCount(1, $proposals);
        $proposal = $proposals[0];
        self::assertSame(Status::Pending, InMemoryRecommendationRepository::stored($proposal));
        self::assertSame(150, $proposal->proposal()?->byKcal);
        self::assertSame('fake', $proposal->message()->provider);
        self::assertSame(1, $this->phrased());
        $made = $this->events->published[0];
        self::assertInstanceOf(RecommendationMade::class, $made);
        self::assertSame([$proposal->id()->value, self::ALEX, 'calories.increase', 'fake'], [$made->recommendationId->value, $made->userId->value, $made->kind->value, $made->provider]);
        $settings = $this->settings->ofOrDefault($this->alex, $this->clock->now());
        self::assertTrue($settings->hasAnalysed(self::day(self::TODAY)));
        self::assertSame(100, $settings->adherence()?->score(), 'the adherence of the week is kept with the analysis');
    }

    public function testTheAnalysisRunsOnceADayUnlessAskedAgain(): void
    {
        $this->stalledGain();
        $this->analyse();
        // Eating dropped: the data now supports another recommendation.
        $this->stalledGain(1850.0);

        $this->analyse();
        self::assertSame(['calories.increase'], $this->openKinds(), 'the same day, nothing ran');

        $this->analyse(again: true);
        self::assertSame(['calories.reach_target_first'], $this->openKinds());
        self::assertSame(Status::Expired, InMemoryRecommendationRepository::stored($this->recommendations->ofKind('calories.increase')[0]));

        $this->analyse(today: '2026-09-30');
        self::assertSame(['calories.reach_target_first'], $this->openKinds(), 'a new day runs it, the same finding is kept');
    }

    public function testARecommendationStillSupportedIsKeptAsItWas(): void
    {
        $this->stalledGain();
        $this->analyse();
        $first = $this->recommendations->ofKind('calories.increase')[0];
        $this->settings->ofOrDefault($this->alex, $this->clock->now())->change(Personality::Encouraging, null, $this->clock->now());

        $this->clock->modify('+2 hours');
        $this->analyse(again: true);

        self::assertCount(1, $this->recommendations->recommendations);
        self::assertSame([$first], $this->recommendations->openOf($this->alex));
        self::assertSame(1, $this->phrased(), 'not phrased again, even in another tone');
    }

    public function testAtMostThreeAreOfferedTheMostImportantFirst(): void
    {
        $this->stalledGain(protein: 90.0);
        $this->weight->reading = new WeightReading(60.0, 0.0, 2, 20);
        $this->training->reading = new TrainingReading(2, 3, 1, [new ExerciseStall('Squat', 5, self::day('2026-08-01'))]);

        $this->analyse();

        self::assertCount(3, $this->recommendations->recommendations);
        self::assertSame(['calories.increase', 'protein.low', 'training.stall'], $this->shownKinds());
    }

    public function testOneOutOfTheFirstThreeExpires(): void
    {
        $this->stalledGain();
        $this->weight->reading = new WeightReading(60.0, 0.5, 5, 20);
        $this->training->reading = new TrainingReading(3, 3, 2, []);
        $this->analyse();
        self::assertSame(['training.records'], $this->openKinds());

        // Three more important findings come.
        $this->stalledGain(protein: 90.0);
        $this->training->reading = new TrainingReading(3, 3, 2, [new ExerciseStall('Squat', 5, self::day('2026-08-01'))]);
        $this->analyse(again: true);

        self::assertSame(['calories.increase', 'protein.low', 'training.stall'], $this->shownKinds());
        self::assertSame(Status::Expired, InMemoryRecommendationRepository::stored($this->recommendations->ofKind('training.records')[0]));
    }

    public function testAKindJustDecidedIsNotOfferedAgainForAWeek(): void
    {
        $this->stalledGain();
        $this->analyse();
        $this->recommendations->ofKind('calories.increase')[0]->reject($this->clock->now());

        $this->clock->modify('+6 days 23 hours');
        $this->analyse(today: '2026-10-06');
        self::assertSame([], $this->openKinds());

        $this->clock->modify('+61 minutes');
        $this->analyse(again: true, today: '2026-10-06');
        self::assertSame(['calories.increase'], $this->openKinds());
    }

    public function testAPostponedOneIsKeptNotOfferedAgain(): void
    {
        $this->stalledGain();
        $this->analyse();
        $this->recommendations->ofKind('calories.increase')[0]->postpone($this->clock->now());

        $this->analyse(again: true);

        self::assertCount(1, $this->recommendations->recommendations);
        self::assertSame(Status::Postponed, InMemoryRecommendationRepository::stored($this->recommendations->ofKind('calories.increase')[0]));
    }

    public function testATooOldOneIsReplacedByAFreshOne(): void
    {
        $this->stalledGain();
        $this->analyse();
        $this->clock->modify('+7 days');

        $this->analyse(today: '2026-10-06');

        $proposals = $this->recommendations->ofKind('calories.increase');
        self::assertCount(2, $proposals);
        self::assertSame([Status::Expired, Status::Pending], array_map(InMemoryRecommendationRepository::stored(...), $proposals));
    }

    public function testThePaceSetForTheCoachWinsOverTheNutritionOne(): void
    {
        $this->stalledGain();
        $settings = CoachSettings::defaults($this->alex, $this->clock->now());
        $settings->change(Personality::Neutral, 0.0, $this->clock->now());
        $this->settings->save($settings);

        $this->analyse();

        self::assertSame([], $this->recommendations->ofKind('calories.increase'), 'a stable weight is on pace for a maintenance');
    }

    public function testTheLastFinishedWeekGetsItsReportOnce(): void
    {
        $this->stalledGain();
        $this->weight->byDay['2026-09-20'] = new WeightReading(59.6, 0.0, 5, 20);
        $this->weight->byDay['2026-09-27'] = new WeightReading(60.0, 0.0, 5, 20);
        $this->analyse();

        $reports = $this->reports->latestOf($this->alex, 10);
        self::assertCount(1, $reports);
        $report = $reports[0];
        self::assertSame('2026-09-21', $report->weekStart()->format('Y-m-d'));
        self::assertSame([
            'startKg' => 59.6, 'endKg' => 60.0, 'kgPerWeek' => 0.0, 'paceKgPerWeek' => 0.5, 'averageKcal' => 2200.0, 'averageProtein' => 130.0,
            'loggedDays' => 7, 'targetKcal' => 2200, 'targetProtein' => 120, 'sessions' => 3, 'plannedSessions' => 3, 'records' => 0,
        ], $report->figures()->toArray());
        self::assertSame(Pace::Below, $report->verdict());
        self::assertSame($this->recommendations->ofKind('calories.increase')[0]->message()->action, $report->action(), 'the action of the most important recommendation');
        self::assertSame(100, $report->adherence()->score());
        self::assertContains(['2026-09-21', '2026-09-27'], $this->training->asked);

        $this->analyse(again: true);
        self::assertCount(1, $this->reports->reports);
    }

    public function testAReportAsksForNothingWhenOnlyInformationIsOffered(): void
    {
        $this->stalledGain();
        $this->weight->reading = new WeightReading(60.0, 0.5, 5, 20);
        $this->training->reading = new TrainingReading(3, 3, 2, []);

        $this->analyse();

        self::assertSame(['training.records'], $this->openKinds());
        $report = $this->reports->latestOf($this->alex, 1)[0] ?? null;
        self::assertNotNull($report);
        self::assertNull($report->action());
        self::assertSame(Pace::OnTrack, $report->verdict());
    }

    public function testAnEmptyWeekGetsNoReport(): void
    {
        $this->analyse();

        self::assertSame([], $this->reports->reports);
        self::assertSame(['calories.no_target', 'weigh_ins.few'], $this->shownKinds());
        self::assertNull($this->settings->ofOrDefault($this->alex, $this->clock->now())->adherence()?->score(), 'nothing recorded: no score');
    }

    public function testEachUserHasTheirOwnCoach(): void
    {
        $this->stalledGain();
        $this->analyse();

        $this->analyse(userId: $this->sam);

        self::assertSame(['calories.increase'], $this->openKinds());
        $sams = array_map(static fn (Recommendation $recommendation): string => $recommendation->kind()->value, $this->recommendations->openOf($this->sam));
        self::assertEqualsCanonicalizing(['calories.no_target', 'weigh_ins.few'], $sams, 'Sam has none of Alex\'s data');
        self::assertTrue($this->settings->ofOrDefault($this->sam, $this->clock->now())->hasAnalysed(self::day(self::TODAY)));
    }

    public function testTheAnalysisNeverGoesBackToAnEarlierDay(): void
    {
        $this->stalledGain();
        $this->analyse(today: '2026-09-30');
        $this->stalledGain(1850.0);

        $this->analyse(today: self::TODAY);

        self::assertSame(['calories.increase'], $this->openKinds(), 'a device a day behind does not run it again');
    }

    public function testExerciseNamesNeverReachTheProviderButComeBackInTheMessage(): void
    {
        $this->stalledGain();
        $this->training->reading = new TrainingReading(3, 3, 0, [new ExerciseStall('Squat de Léa', 5, self::day('2026-08-01'))]);
        $provider = new class implements AiProvider {
            public ?CoachAnalysis $received = null;

            public function generateRecommendation(CoachAnalysis $analysis): CoachMessage
            {
                $this->received = $analysis;

                return new FakeAiProvider()->generateRecommendation($analysis);
            }
        };
        $handler = new AnalyseCoachHandler($this->settings, $this->recommendations, $this->reports, $this->reader(), $provider, $this->events, $this->uuids, $this->clock);

        $handler(new AnalyseCoach($this->alex, self::day(self::TODAY), false));

        $stall = $this->recommendations->ofKind('training.stall')[0];
        self::assertStringNotContainsString('Léa', (string) json_encode($provider->received?->finding->figures));
        self::assertSame('{exercise1}', $provider->received?->finding->figures['exercises'][0]['exercise'] ?? null);
        self::assertStringContainsString('Squat de Léa', $stall->message()->title);
        self::assertStringContainsString('Squat de Léa', $stall->message()->why[0]);
        self::assertSame('Squat de Léa', $stall->figures()['exercises'][0]['exercise'] ?? null, 'the figures kept are the real ones');
    }
}
