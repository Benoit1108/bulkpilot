<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine\Type;

use App\Progression\Domain\Weight\WeighInId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class WeighInIdType extends UuidType
{
    public const string NAME = 'weigh_in_id';

    protected function identifierClass(): string
    {
        return WeighInId::class;
    }
}
