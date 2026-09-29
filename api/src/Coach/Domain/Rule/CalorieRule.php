<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\Pace;
use App\Shared\Domain\Calendar\Days;

/**
 * Whether the calories should change (spec §64–66), judged on the weight trend and on what was
 * really eaten: a target is only raised once it is reached, and only lowered once it is kept to.
 */
final readonly class CalorieRule implements Rule
{
    /** A target is given this long before it is judged (the whole window eaten under it). */
    public const int HOLD_DAYS = Facts::INTAKE_DAYS;
    /** Days logged, of the window, to judge what was eaten. */
    public const int MIN_LOGGED_DAYS = 10;
    /** Weigh-ins, of the last 4 weeks, to trust the trend. */
    public const int MIN_WEIGH_INS = 8;
    /** Towards the goal (more to gain, more to lose), a step of this size; back from it, a smaller one. */
    public const int GOAL_STEP_KCAL = 150;
    public const int EASE_STEP_KCAL = 100;
    /** Below this share of the target, reach it before raising it; above the other, keep to it before lowering it. */
    public const float REACHED_SHARE = 0.95;
    public const float KEPT_SHARE = 1.05;
    /** So far below the target that reaching it comes first. */
    public const float FAR_BELOW_SHARE = 0.85;
    /** The bounds of a nutrition target: a proposal never leaves them. */
    public const int MIN_TARGET_KCAL = 1000;
    public const int MAX_TARGET_KCAL = 7000;

    public function evaluate(Facts $facts): ?Finding
    {
        $target = $facts->target;
        if (null === $target) {
            return new Finding(Kind::NoTarget, Level::Info, []);
        }
        if (null === $facts->pace) {
            return new Finding(Kind::NoPace, Level::Info, ['targetKcal' => $target->energyKcal]);
        }
        $ratio = $facts->kcalRatio();
        $pace = Pace::of($facts->pace, $facts->weight->kgPerWeek);
        if (Days::between($target->effectiveFrom, $facts->today) < self::HOLD_DAYS
            || $facts->intake->logged() < self::MIN_LOGGED_DAYS
            || $facts->weight->weighIns28 < self::MIN_WEIGH_INS
            || null === $ratio
            || \in_array($pace, [Pace::OnTrack, Pace::Unknown], true)) {
            return null;
        }

        $figures = [
            'days' => $facts->intake->windowDays,
            'loggedDays' => $facts->intake->logged(),
            'averageKcal' => $facts->intake->averageKcal(),
            'targetKcal' => $target->energyKcal,
            'percentOfTarget' => self::percent($ratio),
            'kgPerWeek' => $facts->weight->kgPerWeek,
            'paceKgPerWeek' => $facts->pace,
            'average7Kg' => $facts->weight->average7Kg,
        ];
        if (Pace::Below === $pace) {
            if ($ratio < self::REACHED_SHARE) {
                return new Finding(Kind::ReachTargetFirst, $ratio < self::FAR_BELOW_SHARE ? Level::Priority : Level::Watch, $figures);
            }
            $step = min($facts->pace > 0 ? self::GOAL_STEP_KCAL : self::EASE_STEP_KCAL, self::MAX_TARGET_KCAL - $target->energyKcal);

            return $step > 0 ? new Finding(Kind::IncreaseCalories, Level::Priority, $figures, new CalorieProposal($target->energyKcal, $step)) : null;
        }
        if ($ratio > self::KEPT_SHARE) {
            return new Finding(Kind::BackToTarget, Level::Watch, $figures);
        }
        $step = min($facts->pace < 0 ? self::GOAL_STEP_KCAL : self::EASE_STEP_KCAL, $target->energyKcal - self::MIN_TARGET_KCAL);

        return $step > 0 ? new Finding(Kind::DecreaseCalories, Level::Priority, $figures, new CalorieProposal($target->energyKcal, -$step)) : null;
    }

    /**
     * The share eaten, in whole percent rounded down: 94.96 % reads 94 %, never 95 % next to
     * "reach the target first".
     */
    public static function percent(float $ratio): int
    {
        return (int) floor(round($ratio * 100, 6));
    }
}
