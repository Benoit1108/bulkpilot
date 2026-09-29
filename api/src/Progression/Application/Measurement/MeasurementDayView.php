<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\MeasurementDay;

final readonly class MeasurementDayView
{
    /**
     * @param array<string, float> $centimetres by measure key
     */
    public function __construct(public \DateTimeImmutable $day, public array $centimetres, public \DateTimeImmutable $recordedAt)
    {
    }

    public static function of(MeasurementDay $day): self
    {
        return new self($day->day(), $day->values(), $day->updatedAt());
    }
}
