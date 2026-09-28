<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Presentation\Api\Food\CustomFoodInput;
use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmProductInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(Barcode::INPUT_PATTERN)]
    public string $barcode = '';

    /** The user's own values; without them, the product is kept as Open Food Facts gives it. */
    #[Assert\Valid]
    public ?CustomFoodInput $corrections = null;
}
