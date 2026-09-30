<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

final readonly class DayWeighing
{
    /**
     * @param list<ReachedStep> $reached the steps of the goal followed that a weigh-in reached, the first reached first
     */
    public function __construct(
        public bool $weighedToday,
        /** Days weighed among the 7 before that day. */
        public int $weighInsLastWeek,
        public array $reached,
    ) {
    }
}
