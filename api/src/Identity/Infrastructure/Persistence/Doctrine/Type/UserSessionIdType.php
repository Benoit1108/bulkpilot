<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class UserSessionIdType extends UuidType
{
    public const string NAME = 'user_session_id';

    protected function identifierClass(): string
    {
        return UserSessionId::class;
    }
}
