<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Token\OneTimeTokenId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class OneTimeTokenIdType extends UuidType
{
    public const string NAME = 'one_time_token_id';

    protected function identifierClass(): string
    {
        return OneTimeTokenId::class;
    }
}
