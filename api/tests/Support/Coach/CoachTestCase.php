<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Coach\Application\Analysis\AnalyseCoach;
use App\Coach\Application\Analysis\AnalyseCoachHandler;
use App\Coach\Application\Analysis\FactsReader;
use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\Stall;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Analysis\WeightFacts;
use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Infrastructure\Ai\FakeAiProvider;
use App\Shared\Application\Coaching\TargetReading;
use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\WeightReading;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Tests\Support\Shared\SpyEventBus;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Coach rules and use cases without AI (docs/TESTING.md): in-memory adapters, the template
 * provider, and the clock on Tuesday 2026-09-29 (the week before runs from Monday 21 to Sunday 27).
 */
abstract class CoachTestCase extends TestCase
{
    protected const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    protected const string SAM = '00000000-0000-7000-8000-000000000511';
    protected const string TODAY = '2026-09-29';

    protected InMemoryRecommendationRepository $recommendations;
    protected InMemoryCoachSettingsRepository $settings;
    protected InMemoryWeeklyReportRepository $reports;
    protected FakeNutritionReadings $nutrition;
    protected FakeBodyWeight $weight;
    protected FakeTrainingReadings $training;
    protected SpyCalorieTargets $targets;
    protected SpyEventBus $events;
    protected SequentialUuidGenerator $uuids;
    protected TestHandler $audit;
    protected MockClock $clock;
    protected UserId $alex;
    protected UserId $sam;

    protected function setUp(): void
    {
        $this->recommendations = new InMemoryRecommendationRepository();
        $this->settings = new InMemoryCoachSettingsRepository();
        $this->reports = new InMemoryWeeklyReportRepository();
        $this->nutrition = new FakeNutritionReadings(self::ALEX);
        $this->weight = new FakeBodyWeight(self::ALEX);
        $this->training = new FakeTrainingReadings(self::ALEX);
        $this->targets = new SpyCalorieTargets();
        $this->events = new SpyEventBus();
        $this->uuids = new SequentialUuidGenerator();
        $this->audit = new TestHandler();
        $this->clock = new MockClock(self::TODAY.' 08:00:00');
        $this->alex = new UserId(self::ALEX);
        $this->sam = new UserId(self::SAM);
    }

    protected static function day(string $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day);
    }

    /**
     * @return list<array{kcal: float, protein: float}>
     */
    protected static function eaten(int $days, float $kcal, float $protein = 130.0): array
    {
        return array_fill(0, $days, ['kcal' => $kcal, 'protein' => $protein]);
    }

    /**
     * Facts on 2026-09-29, by default: a 2 200 kcal / 120 g target in force for 4 weeks, a gain
     * of 0.5 kg a week aimed at, a stable weight weighed often, every day eaten on target.
     */
    protected static function facts(
        ?TargetFacts $target = new TargetFacts(new \DateTimeImmutable('2026-09-01'), 2200, 120),
        ?float $pace = 0.5,
        WeightFacts $weight = new WeightFacts(60.0, 0.0, 5, 20),
        ?IntakeFacts $intake = null,
        ?IntakeFacts $week = null,
        TrainingFacts $training = new TrainingFacts(3, 3, 0, []),
    ): Facts {
        return new Facts(
            self::day(self::TODAY),
            $target,
            $pace,
            $weight,
            $intake ?? new IntakeFacts(Facts::INTAKE_DAYS, self::eaten(14, 2200)),
            $week ?? new IntakeFacts(Facts::WEEK_DAYS, self::eaten(7, 2200)),
            $training,
        );
    }

    /**
     * @return list<Stall>
     */
    protected static function stalls(int $count): array
    {
        return array_map(static fn (int $n): Stall => new Stall('Exercice '.$n, 4 + $n, new \DateTimeImmutable('2026-08-01')), range(1, $count));
    }

    protected function recommendation(Finding $finding, ?UserId $owner = null): Recommendation
    {
        $recommendation = Recommendation::make(
            new RecommendationId($this->uuids->generate()),
            $owner ?? $this->alex,
            $finding,
            new CoachMessage('Titre '.$finding->kind->value, 'Corps', ['Pourquoi'], 'Action '.$finding->kind->value, FakeAiProvider::NAME),
            $this->clock->now(),
        );
        // Offered: its announcement is not what the tests look at.
        $recommendation->pullDomainEvents();
        $this->recommendations->save($recommendation);

        return $recommendation;
    }

    /**
     * A user gaining 0.5 kg a week on 2 200 kcal, from 2026-09-01, who eats that every day from
     * 2026-09-15 to the end of October, with a stable weight: the Coach proposes +150 kcal.
     */
    protected function stalledGain(float $kcal = 2200.0, float $protein = 130.0): void
    {
        $this->nutrition->target = new TargetReading(self::day('2026-09-01'), 2200, 120, 0.5);
        $this->nutrition->days = [];
        $days = [];
        for ($day = self::day('2026-09-15'); $day <= self::day('2026-10-31'); $day = $day->modify('+1 day')) {
            $days[$day->format('Y-m-d')] = [$kcal, $protein];
        }
        $this->nutrition->log($days);
        $this->weight->reading = new WeightReading(60.0, 0.0, 5, 20);
        $this->training->reading = new TrainingReading(3, 3, 0, []);
    }

    protected function analyse(bool $again = false, string $today = self::TODAY, ?UserId $userId = null): void
    {
        $handler = new AnalyseCoachHandler(
            $this->settings,
            $this->recommendations,
            $this->reports,
            $this->reader(),
            new FakeAiProvider(),
            $this->events,
            $this->uuids,
            $this->clock,
        );
        $handler(new AnalyseCoach($userId ?? $this->alex, self::day($today), $again));
    }

    protected function reader(): FactsReader
    {
        return new FactsReader($this->nutrition, $this->weight, $this->training);
    }
}
