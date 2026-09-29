<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine\Type;

use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class CustomMeasureIdType extends UuidType
{
    public const string NAME = 'custom_measure_id';

    protected function identifierClass(): string
    {
        return CustomMeasureId::class;
    }
}
