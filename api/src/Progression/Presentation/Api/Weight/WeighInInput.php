<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use App\Progression\Domain\Weight\Weight;
use Symfony\Component\Validator\Constraints as Assert;

final class WeighInInput
{
    /** Kilograms, to the tenth. */
    #[Assert\NotNull]
    #[Assert\Range(min: Weight::MIN_KG, max: Weight::MAX_KG)]
    public ?float $weightKg = null;
}
