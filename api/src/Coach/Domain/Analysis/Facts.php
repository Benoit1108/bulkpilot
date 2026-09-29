<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * Everything the rules judge, computed by Symfony (spec §74: the AI never computes). Today is the
 * user's and is left out of every window: it is not over.
 */
final readonly class Facts
{
    /** Days of eating the calorie rules look at. */
    public const int INTAKE_DAYS = 14;
    /** Days of eating, training and weighing the weekly rules look at. */
    public const int WEEK_DAYS = 7;

    public function __construct(
        public \DateTimeImmutable $today,
        public ?TargetFacts $target,
        /** The weight change aimed at, kg per week, signed; null when unknown. */
        public ?float $pace,
        public WeightFacts $weight,
        /** The last {@see INTAKE_DAYS} days. */
        public IntakeFacts $intake,
        /** The last {@see WEEK_DAYS} days. */
        public IntakeFacts $week,
        /** The last {@see WEEK_DAYS} days. */
        public TrainingFacts $training,
    ) {
    }

    /** The share of the calorie target eaten on the days logged of the last {@see INTAKE_DAYS}, null without a target or a log. */
    public function kcalRatio(): ?float
    {
        $average = $this->intake->averageKcal();

        return null === $this->target || null === $average ? null : $average / $this->target->energyKcal;
    }
}
