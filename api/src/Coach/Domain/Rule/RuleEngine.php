<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * Runs every rule on the facts (spec §64) and orders what they found: the most important first,
 * then in the order of their kinds (eating before training, advice before information).
 */
final class RuleEngine
{
    /** At most this many recommendations are going at a time (spec §68). */
    public const int MAX_RECOMMENDATIONS = 3;

    /**
     * @return list<Finding>
     */
    public static function findings(Facts $facts): array
    {
        $rules = [new CalorieRule(), new ProteinRule(), new StallRule(), new SessionsRule(), new JournalRule(), new WeighInRule(), new RecordsRule()];
        $findings = array_values(array_filter(array_map(static fn (Rule $rule): ?Finding => $rule->evaluate($facts), $rules)));
        usort($findings, static fn (Finding $a, Finding $b): int => Kind::compare($a->level, $a->kind, $b->level, $b->kind));

        return $findings;
    }
}
