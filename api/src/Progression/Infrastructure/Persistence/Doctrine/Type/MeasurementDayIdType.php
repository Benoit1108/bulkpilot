<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine\Type;

use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class MeasurementDayIdType extends UuidType
{
    public const string NAME = 'measurement_day_id';

    protected function identifierClass(): string
    {
        return MeasurementDayId::class;
    }
}
