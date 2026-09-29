<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Application\Measurement\MeasurementDayView;
use App\Progression\Domain\Weight\DayWeight;

/**
 * The photos of a day (spec §61: « Début — 45 kg vs Aujourd'hui — 51,2 kg »), with the weight and
 * the measurements of that day, or the latest before it (their own day says which).
 */
final readonly class PhotoSessionView
{
    /**
     * @param list<PhotoView>       $photos
     * @param array<string, string> $measureNames the user's own measures by key (body parts are named by the app)
     */
    public function __construct(
        public \DateTimeImmutable $day,
        public ?DayWeight $weight,
        public ?MeasurementDayView $measurements,
        public array $photos,
        public array $measureNames,
    ) {
    }
}
