<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * A day's weight was recorded or corrected (spec §99: WeightMeasurementRecorded, for the Coach).
 */
final readonly class WeighInRecorded implements DomainEvent
{
    public function __construct(
        public WeighInId $weighInId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public Weight $weight,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
