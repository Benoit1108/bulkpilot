<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

/**
 * How a finding is put to the user (spec §69: with its "why"), and who phrased it (spec §122).
 */
final readonly class CoachMessage
{
    /**
     * @param list<string> $why the reasons, one per line, figures included
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $why,
        /** What to do, in the imperative and whatever the tone (the weekly report's "Action", spec §59). */
        public string $action,
        public string $provider,
    ) {
    }
}
