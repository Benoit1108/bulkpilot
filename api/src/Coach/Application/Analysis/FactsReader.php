<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\IntakeFacts;
use App\Coach\Domain\Analysis\Stall;
use App\Coach\Domain\Analysis\TargetFacts;
use App\Coach\Domain\Analysis\TrainingFacts;
use App\Coach\Domain\Analysis\WeightFacts;
use App\Coach\Domain\Report\WeekFacts;
use App\Coach\Domain\Report\WeekFigures;
use App\Shared\Application\Coaching\BodyWeight;
use App\Shared\Application\Coaching\DayIntake;
use App\Shared\Application\Coaching\ExerciseStall;
use App\Shared\Application\Coaching\NutritionReadings;
use App\Shared\Application\Coaching\TargetReading;
use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\TrainingReadings;
use App\Shared\Application\Coaching\WeightReading;
use App\Shared\Domain\Identifier\UserId;

/**
 * Reads what the Coach judges from the other contexts (ADR-0028), for a day or for a week.
 */
final readonly class FactsReader
{
    public function __construct(private NutritionReadings $nutrition, private BodyWeight $weight, private TrainingReadings $training)
    {
    }

    /**
     * The facts on the user's today: eating and training up to yesterday (today is not over),
     * the weight with this morning's weigh-in, the target in force today (one set today counts).
     *
     * @param ?float $pace the pace the user set for the Coach, over the one of the nutrition estimate
     */
    public function on(UserId $userId, \DateTimeImmutable $today, ?float $pace): Facts
    {
        $today = $today->setTime(0, 0);
        $yesterday = $today->modify('-1 day');
        $target = $this->nutrition->targetOn($userId, $today);
        $days = $this->nutrition->intake($userId, $today->modify(\sprintf('-%d days', Facts::INTAKE_DAYS)), $yesterday);
        $weekStart = $today->modify(\sprintf('-%d days', Facts::WEEK_DAYS));

        return new Facts(
            $today,
            self::target($target),
            $pace ?? $target?->weeklyChangeKg,
            self::weight($this->weight->reading($userId, $today)),
            self::intake($days, Facts::INTAKE_DAYS),
            self::intake(array_values(array_filter($days, static fn (DayIntake $day): bool => $day->day >= $weekStart)), Facts::WEEK_DAYS),
            self::training($this->training->between($userId, $weekStart, $yesterday)),
        );
    }

    /**
     * A finished week, Monday to Sunday: its figures and its adherence.
     */
    public function week(UserId $userId, \DateTimeImmutable $monday, ?float $pace): WeekFacts
    {
        $sunday = $monday->modify('+6 days');
        $target = $this->nutrition->targetOn($userId, $sunday);
        $intake = self::intake($this->nutrition->intake($userId, $monday, $sunday), Facts::WEEK_DAYS);
        $before = $this->weight->reading($userId, $monday->modify('-1 day'));
        $end = $this->weight->reading($userId, $sunday);
        $training = $this->training->between($userId, $monday, $sunday);

        return new WeekFacts(
            new WeekFigures(
                $before->average7Kg,
                $end->average7Kg,
                $end->kgPerWeek,
                $pace ?? $target?->weeklyChangeKg,
                $intake->averageKcal(),
                $intake->averageProtein(),
                $intake->logged(),
                $target?->energyKcal,
                $target?->proteinGrams,
                $training->sessions,
                $training->plannedPerWeek,
                $training->records,
            ),
            Adherence::of(self::target($target), $intake, $end->weighIns7, self::training($training)),
        );
    }

    private static function target(?TargetReading $target): ?TargetFacts
    {
        return null === $target ? null : new TargetFacts($target->effectiveFrom, $target->energyKcal, $target->proteinGrams);
    }

    /**
     * @param list<DayIntake> $days
     */
    private static function intake(array $days, int $windowDays): IntakeFacts
    {
        return new IntakeFacts($windowDays, array_map(static fn (DayIntake $day): array => ['kcal' => $day->energyKcal, 'protein' => $day->proteinGrams], $days));
    }

    private static function weight(WeightReading $weight): WeightFacts
    {
        return new WeightFacts($weight->average7Kg, $weight->kgPerWeek, $weight->weighIns7, $weight->weighIns28);
    }

    private static function training(TrainingReading $training): TrainingFacts
    {
        return new TrainingFacts(
            $training->sessions,
            $training->plannedPerWeek,
            $training->records,
            array_map(static fn (ExerciseStall $stall): Stall => new Stall($stall->exerciseName, $stall->sessions, $stall->since), $training->stalls),
        );
    }
}
