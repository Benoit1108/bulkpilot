<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

/**
 * What a rule found, with the figures that led to it (spec §70: kept with the recommendation, and
 * all the AI is given to phrase it, spec §74).
 *
 * @phpstan-type Figures array<string, int|float|string|null|list<array{exercise: string, sessions: int, since: string}>>
 */
final readonly class Finding
{
    /**
     * @param Figures $figures
     */
    public function __construct(
        public Kind $kind,
        public Level $level,
        public array $figures,
        public ?CalorieProposal $proposal = null,
    ) {
    }
}
