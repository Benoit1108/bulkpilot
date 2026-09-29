<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Runs the rules on the user's data (spec §62): once a day when the Coach is opened, or again
 * when the user asks.
 */
final readonly class AnalyseCoach implements Command
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $today, public bool $again)
    {
    }
}
