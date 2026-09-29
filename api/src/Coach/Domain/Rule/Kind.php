<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

/**
 * What a recommendation is about, in the order of the rules that find them (eating before
 * training, advice before information): the order recommendations of the same level come in.
 */
enum Kind: string
{
    case NoTarget = 'calories.no_target';
    case NoPace = 'calories.no_pace';
    case IncreaseCalories = 'calories.increase';
    case DecreaseCalories = 'calories.decrease';
    case ReachTargetFirst = 'calories.reach_target_first';
    case BackToTarget = 'calories.back_to_target';
    case LowProtein = 'protein.low';
    case Stall = 'training.stall';
    case MissedSessions = 'training.missed_sessions';
    case IncompleteJournal = 'journal.incomplete';
    case FewWeighIns = 'weigh_ins.few';
    case Records = 'training.records';

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** The most important first: by level, then in the order above. */
    public static function compare(Level $levelA, self $a, Level $levelB, self $b): int
    {
        return [$levelA->rank(), $a->rank()] <=> [$levelB->rank(), $b->rank()];
    }
}
