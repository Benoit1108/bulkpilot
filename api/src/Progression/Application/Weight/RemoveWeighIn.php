<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Removes a day's weigh-in (a wrong one); removing it again changes nothing.
 */
final readonly class RemoveWeighIn implements Command
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day)
    {
    }
}
