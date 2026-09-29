<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Removes the measurements of a day; removing them again changes nothing.
 */
final readonly class RemoveMeasurements implements Command
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day)
    {
    }
}
