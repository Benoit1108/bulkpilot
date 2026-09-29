<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Progression\Domain\Measurement\CustomMeasure;

/**
 * Two measures of one's own are never named alike (case aside): the charts would not tell them apart.
 */
final class MeasureNames
{
    /**
     * @param list<CustomMeasure> $measures
     */
    public static function assertFree(string $name, array $measures, ?CustomMeasure $renamed = null): void
    {
        foreach ($measures as $measure) {
            if ($measure !== $renamed && mb_strtolower($measure->name()) === mb_strtolower($name)) {
                throw InvalidMeasurement::because('a measure of that name already exists');
            }
        }
    }
}
