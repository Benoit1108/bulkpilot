<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use App\Nutrition\Domain\Food\Portion;
use Symfony\Component\Validator\Constraints as Assert;

final class PortionInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Portion::MAX_LABEL_LENGTH, normalizer: 'trim')]
    public string $label = '';

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(Portion::MAX_GRAMS)]
    public ?float $grams = null;
}
