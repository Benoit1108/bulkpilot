<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

/**
 * What the user answers (spec §132: Accepter, Refuser, Plus tard).
 */
enum Decision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case Postpone = 'postpone';
}
