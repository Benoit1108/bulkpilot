<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\UserId;

final class UserIdType extends UuidType
{
    public const string NAME = 'user_id';

    protected function identifierClass(): string
    {
        return UserId::class;
    }
}
