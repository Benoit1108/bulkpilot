<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Persistence\Doctrine\Type;

use App\Export\Domain\ExportId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class ExportIdType extends UuidType
{
    public const string NAME = 'export_id';

    protected function identifierClass(): string
    {
        return ExportId::class;
    }
}
