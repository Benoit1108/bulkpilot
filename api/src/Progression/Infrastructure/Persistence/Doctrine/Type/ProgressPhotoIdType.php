<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine\Type;

use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class ProgressPhotoIdType extends UuidType
{
    public const string NAME = 'progress_photo_id';

    protected function identifierClass(): string
    {
        return ProgressPhotoId::class;
    }
}
