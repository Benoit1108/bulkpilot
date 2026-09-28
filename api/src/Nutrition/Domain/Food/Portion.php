<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

use App\Nutrition\Domain\Exception\InvalidPortion;

/**
 * A named household measure: "1 œuf" = 60 g, "1 bol" = 250 g.
 */
final readonly class Portion
{
    public const int MAX_LABEL_LENGTH = 40;
    public const float MAX_GRAMS = 5000.0;

    public string $label;

    public function __construct(string $label, public float $grams)
    {
        $label = trim($label);
        if ('' === $label || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw InvalidPortion::because('a portion needs a label of 1 to 40 characters');
        }
        if ($grams <= 0 || $grams > self::MAX_GRAMS) {
            throw InvalidPortion::because('a portion weighs between 0 and 5000 g');
        }

        $this->label = $label;
    }
}
