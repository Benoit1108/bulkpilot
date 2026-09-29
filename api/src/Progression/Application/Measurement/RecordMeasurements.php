<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Records the measurements of a day, or replaces them (one set a day).
 */
final readonly class RecordMeasurements implements Command
{
    /**
     * @param array<string, float> $centimetres by measure key
     */
    public function __construct(
        public MeasurementDayId $dayId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public array $centimetres,
    ) {
    }
}
