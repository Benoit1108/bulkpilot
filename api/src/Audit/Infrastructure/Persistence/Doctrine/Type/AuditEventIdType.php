<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence\Doctrine\Type;

use App\Audit\Domain\AuditEventId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class AuditEventIdType extends UuidType
{
    public const string NAME = 'audit_event_id';

    protected function identifierClass(): string
    {
        return AuditEventId::class;
    }
}
