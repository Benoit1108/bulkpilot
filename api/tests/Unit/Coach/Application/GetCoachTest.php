<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Application;

use App\Coach\Application\Analysis\CoachView;
use App\Coach\Application\Analysis\GetCoach;
use App\Coach\Application\Analysis\GetCoachHandler;
use App\Coach\Application\Recommendation\RecommendationView;
use App\Coach\Domain\Analysis\AdherenceComponent;
use App\Coach\Domain\Rule\CalorieProposal;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Rule\Level;
use App\Coach\Domain\Settings\Personality;
use App\Shared\Application\Coaching\TrainingReading;
use App\Tests\Support\Coach\CoachTestCase;

final class GetCoachTest extends CoachTestCase
{
    private function coach(): CoachView
    {
        return (new GetCoachHandler($this->settings, $this->recommendations, $this->reports, $this->clock))(new GetCoach($this->alex));
    }

    /**
     * @return list<string>
     */
    private static function kinds(CoachView $coach): array
    {
        return array_map(static fn (RecommendationView $view): string => $view->kind->value, $coach->recommendations);
    }

    public function testAtMostThreeAreShownTheMostImportantFirstPostponedOnesAside(): void
    {
        $this->recommendation(new Finding(Kind::Records, Level::Info, []));
        $this->recommendation(new Finding(Kind::MissedSessions, Level::Watch, []));
        $this->recommendation(new Finding(Kind::IncreaseCalories, Level::Priority, [], new CalorieProposal(2200, 150)));
        $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, []));
        $this->recommendation(new Finding(Kind::Stall, Level::Watch, []))->postpone($this->clock->now());
        $this->recommendation(new Finding(Kind::FewWeighIns, Level::Info, []))->reject($this->clock->now());
        $this->recommendation(new Finding(Kind::LowProtein, Level::Watch, []), $this->sam);

        self::assertSame(['calories.increase', 'protein.low', 'training.missed_sessions'], self::kinds($this->coach()));

        // Two days later, the postponed one is back in its place.
        $this->clock->modify('+2 days');
        self::assertSame(['calories.increase', 'protein.low', 'training.stall'], self::kinds($this->coach()));
    }

    public function testTheAdherenceOfTheLastAnalysisAndTheLastReportComeWithIt(): void
    {
        $this->stalledGain();
        $this->training->reading = new TrainingReading(2, 3, 0, []);
        $this->analyse();

        // What changes after the analysis is not read again: the Coach shows what it found then.
        $this->training->reading = new TrainingReading(0, 3, 0, []);

        $coach = $this->coach();

        self::assertSame(
            [['calories', 7, 7], ['protein', 7, 7], ['training', 2, 3], ['weigh_ins', 5, 3]],
            array_map(static fn (AdherenceComponent $part): array => [$part->part->value, $part->done, $part->expected], $coach->settings->adherence->components ?? []),
        );
        self::assertSame(92, $coach->settings->adherence?->score());
        self::assertSame('2026-09-21', $coach->report?->weekStart->format('Y-m-d'));
        self::assertSame('2026-09-29', $coach->settings->lastAnalysedOn?->format('Y-m-d'));
    }

    public function testBeforeAnyAnalysisTheCoachIsDirectAndEmpty(): void
    {
        $coach = $this->coach();

        self::assertSame([], $coach->recommendations);
        self::assertNull($coach->report);
        self::assertSame(Personality::Direct, $coach->settings->personality);
        self::assertNull($coach->settings->lastAnalysedOn);
        self::assertNull($coach->settings->adherence);
    }
}
