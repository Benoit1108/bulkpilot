<?php

declare(strict_types=1);

namespace App\Coach\Domain\Settings;

/**
 * How the Coach speaks (spec §131). It changes the wording only, never a rule.
 */
enum Personality: string
{
    case Direct = 'direct';
    case Encouraging = 'encouraging';
    case Neutral = 'neutral';
}
