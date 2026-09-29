<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Weight\WeighInId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Records the weight of a day, or replaces it (one weigh-in per day). `$weighInId` is used when
 * the day has none yet.
 */
final readonly class RecordWeighIn implements Command
{
    public function __construct(
        public WeighInId $weighInId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public float $weightKg,
    ) {
    }
}
