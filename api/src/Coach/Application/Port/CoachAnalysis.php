<?php

declare(strict_types=1);

namespace App\Coach\Application\Port;

use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Settings\Personality;

/**
 * All an AI provider is given (spec §74, §122): what a rule found and the figures behind it,
 * computed by Symfony, and the tone to take. Nothing that identifies the user: what the user
 * typed (exercise names) is replaced by placeholders ({@see \App\Coach\Application\Analysis\Placeholders}).
 */
final readonly class CoachAnalysis
{
    public function __construct(public Finding $finding, public Personality $personality)
    {
    }
}
