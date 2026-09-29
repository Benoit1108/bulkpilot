<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

/**
 * What to aim for today, from last time (spec §38). Only ever shown: the user decides.
 */
final readonly class Suggestion
{
    private function __construct(
        public SuggestionReason $reason,
        public ?float $loadKg,
        public ?int $reps,
        public ?int $seconds,
    ) {
    }

    public static function load(SuggestionReason $reason, float $loadKg, int $reps): self
    {
        return new self($reason, $loadKg, $reps, null);
    }

    public static function duration(SuggestionReason $reason, int $seconds): self
    {
        return new self($reason, null, null, $seconds);
    }
}
