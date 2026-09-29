<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Measurement\Length;
use Symfony\Component\Validator\Constraints as Assert;

final class MeasurementsInput
{
    /**
     * @var array<array-key, float> centimetres by measure key (a body part, or the id of one of the user's own)
     */
    #[ApiProperty(schema: ['type' => 'object', 'additionalProperties' => ['type' => 'number'], 'minProperties' => 1])]
    #[Assert\Count(min: 1, max: 30)]
    #[Assert\All([new Assert\NotNull(), new Assert\Type('numeric'), new Assert\Range(min: Length::MIN_CM, max: Length::MAX_CM)])]
    public array $centimetres = [];
}
