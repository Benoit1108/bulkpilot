<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use App\Progression\Domain\Measurement\CustomMeasure;
use Symfony\Component\Validator\Constraints as Assert;

final class MeasureInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: CustomMeasure::MAX_NAME)]
    public string $name = '';
}
