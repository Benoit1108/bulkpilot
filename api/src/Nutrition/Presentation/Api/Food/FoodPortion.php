<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Domain\Food\Portion;

final readonly class FoodPortion
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $label,
        #[ApiProperty(required: true)]
        public float $grams,
    ) {
    }

    public static function of(Portion $portion): self
    {
        return new self($portion->label, $portion->grams);
    }
}
