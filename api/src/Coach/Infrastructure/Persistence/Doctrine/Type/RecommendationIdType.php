<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Persistence\Doctrine\Type;

use App\Coach\Domain\Recommendation\RecommendationId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class RecommendationIdType extends UuidType
{
    public const string NAME = 'recommendation_id';

    protected function identifierClass(): string
    {
        return RecommendationId::class;
    }
}
