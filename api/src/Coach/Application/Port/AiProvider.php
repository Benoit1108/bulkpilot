<?php

declare(strict_types=1);

namespace App\Coach\Application\Port;

use App\Coach\Domain\Recommendation\CoachMessage;

/**
 * Phrases a recommendation (spec §73). It interprets the figures it is given and never computes
 * one: the decision is already made by the rules, the provider only puts it into words.
 */
interface AiProvider
{
    public function generateRecommendation(CoachAnalysis $analysis): CoachMessage;
}
