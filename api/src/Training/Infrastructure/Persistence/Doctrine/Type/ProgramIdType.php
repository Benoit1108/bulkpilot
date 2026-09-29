<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;
use App\Training\Domain\Program\ProgramId;

final class ProgramIdType extends UuidType
{
    public const string NAME = 'program_id';

    protected function identifierClass(): string
    {
        return ProgramId::class;
    }
}
