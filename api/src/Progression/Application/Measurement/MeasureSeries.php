<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\DayLength;

/**
 * One measure over a period: its values, the latest, and the change since the first of the period.
 */
final readonly class MeasureSeries
{
    /**
     * @param list<DayLength> $points the oldest first
     */
    public function __construct(public MeasureView $measure, public ?DayLength $latest, public ?float $changeCm, public array $points)
    {
    }
}
