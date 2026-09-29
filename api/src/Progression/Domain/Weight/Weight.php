<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

use App\Progression\Domain\Exception\InvalidWeighIn;

/**
 * A body weight in kilograms, to the tenth (what a bathroom scale shows).
 */
final readonly class Weight
{
    public const float MIN_KG = 20.0;
    public const float MAX_KG = 400.0;

    public float $kg;

    public function __construct(float $kg)
    {
        $rounded = round($kg, 1);
        if (!is_finite($kg) || $rounded < self::MIN_KG || $rounded > self::MAX_KG) {
            throw InvalidWeighIn::because(\sprintf('a weight is between %.0f and %.0f kg', self::MIN_KG, self::MAX_KG));
        }
        $this->kg = $rounded;
    }

    public function equals(self $other): bool
    {
        return $this->kg === $other->kg;
    }
}
