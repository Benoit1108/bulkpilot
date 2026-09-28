<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\External\ExternalIdentityId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class ExternalIdentityIdType extends UuidType
{
    public const string NAME = 'external_identity_id';

    protected function identifierClass(): string
    {
        return ExternalIdentityId::class;
    }
}
