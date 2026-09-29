<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Stops taking a measure of one's own: it leaves the form, its values stay (archiving again changes nothing).
 */
final readonly class ArchiveMeasure implements Command
{
    public function __construct(public CustomMeasureId $measureId, public UserId $userId)
    {
    }
}
