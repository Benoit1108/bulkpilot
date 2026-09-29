<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\MediaId;

final class MediaIdType extends UuidType
{
    public const string NAME = 'media_id';

    protected function identifierClass(): string
    {
        return MediaId::class;
    }
}
