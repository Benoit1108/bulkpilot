<?php

declare(strict_types=1);

namespace App\Notification\Domain\Notification;

/**
 * What a notification is about (spec §75): each can be turned off.
 */
enum Category: string
{
    case LowCalories = 'low_calories';
    case WorkoutNotStarted = 'workout_not_started';
    case MissingWeighIn = 'missing_weigh_in';
    case GoalReached = 'goal_reached';
    case Record = 'record';
    case WeeklyReport = 'weekly_report';

    /**
     * Every value, for the API schema (attributes take constants only). A test keeps it equal to cases().
     *
     * @var list<string>
     */
    public const array VALUES = [
        self::LowCalories->value,
        self::WorkoutNotStarted->value,
        self::MissingWeighIn->value,
        self::GoalReached->value,
        self::Record->value,
        self::WeeklyReport->value,
    ];
}
