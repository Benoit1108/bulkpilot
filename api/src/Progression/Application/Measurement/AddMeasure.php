<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Adds a measure of the user's own to the body parts.
 */
final readonly class AddMeasure implements Command
{
    public function __construct(public CustomMeasureId $measureId, public UserId $userId, public string $name)
    {
    }
}
