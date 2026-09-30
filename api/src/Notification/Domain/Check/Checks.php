<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Settings\NotificationSettings;

/**
 * Every rule on a situation, then what the user's settings let through: nothing during the quiet
 * hours (spec §76), nothing of a category turned off (spec §75). The checker skips reading the
 * situation at all when nothing could come through.
 */
final class Checks
{
    /**
     * @return list<Draft>
     */
    public static function drafts(Situation $situation, NotificationSettings $settings): array
    {
        if ($settings->isQuietAt($situation->now)) {
            return [];
        }
        $rules = [new LowCaloriesRule(), new WorkoutNotStartedRule(), new MissingWeighInRule(), new GoalReachedRule(), new RecordRule(), new WeeklyReportRule()];
        $drafts = array_merge(...array_map(static fn (Rule $rule): array => $rule->evaluate($situation), $rules));

        return array_values(array_filter($drafts, static fn (Draft $draft): bool => $settings->isEnabled($draft->category)));
    }
}
