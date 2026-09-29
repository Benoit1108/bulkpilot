<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

use App\Progression\Domain\Exception\InvalidMeasurement;

/**
 * A girth in centimetres, to the millimetre (what a tape shows).
 */
final readonly class Length
{
    public const float MIN_CM = 1.0;
    public const float MAX_CM = 300.0;

    public float $cm;

    public function __construct(float $cm)
    {
        $rounded = round($cm, 1);
        if (!is_finite($cm) || $rounded < self::MIN_CM || $rounded > self::MAX_CM) {
            throw InvalidMeasurement::because(\sprintf('a measurement is between %.0f and %.0f cm', self::MIN_CM, self::MAX_CM));
        }
        $this->cm = $rounded;
    }
}
