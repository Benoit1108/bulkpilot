<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

use App\Progression\Domain\Weight\DayWeight;

/**
 * Where the user stands on their goal (spec §52–53): the steps reached (the first weigh-in at
 * or past each, since the goal was set), the share of the way done, the change since the last
 * step reached ("depuis l'objectif précédent"), and estimates to the next step and to the target.
 */
final readonly class GoalProgress
{
    /**
     * @param list<StepProgress> $steps
     */
    private function __construct(
        public WeightGoal $goal,
        public array $steps,
        public int $percent,
        public float $sinceLastStepKg,
        public ?NextStep $next,
        public float $toTargetKg,
        public Estimate $toTarget,
    ) {
    }

    /**
     * @param list<DayWeight> $weighIns the oldest first (those before the goal's start count for nothing)
     */
    public static function of(WeightGoal $goal, array $weighIns, DayWeight $latest, ?float $kgPerWeek): self
    {
        $steps = [];
        foreach ($goal->steps() as $i => $kg) {
            $reached = null;
            foreach ($weighIns as $weighIn) {
                if ($weighIn->day >= $goal->startDay() && $goal->reaches($weighIn->kg, $kg)) {
                    $reached = $weighIn->day;
                    break;
                }
            }
            $steps[] = new StepProgress($kg, $i === \count($goal->milestones()), $reached);
        }

        // Steps are reached in order: the reached ones come first, then the next one.
        $lastReached = null;
        $next = null;
        foreach ($steps as $step) {
            if (null !== $step->reachedOn) {
                $lastReached = $step;
            } elseif (null === $next) {
                $next = $step;
            }
        }
        $share = ($latest->kg - $goal->startKg()) / ($goal->targetKg() - $goal->startKg());
        $toTarget = round($goal->targetKg() - $latest->kg, 1);

        return new self(
            $goal,
            $steps,
            (int) round(max(0.0, min(1.0, $share)) * 100),
            round($latest->kg - ($lastReached->kg ?? $goal->startKg()), 1),
            null === $next ? null : self::nextStep($next, $latest, $kgPerWeek, $goal),
            $toTarget,
            Estimate::toward($toTarget, $kgPerWeek, $goal->direction()),
        );
    }

    private static function nextStep(StepProgress $next, DayWeight $latest, ?float $kgPerWeek, WeightGoal $goal): NextStep
    {
        $left = round($next->kg - $latest->kg, 1);

        return new NextStep($next, $left, Estimate::toward($left, $kgPerWeek, $goal->direction()));
    }
}
