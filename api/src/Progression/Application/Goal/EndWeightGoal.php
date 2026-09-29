<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Stops following a goal (kept, ended); without one, nothing changes.
 */
final readonly class EndWeightGoal implements Command
{
    public function __construct(public UserId $userId)
    {
    }
}
