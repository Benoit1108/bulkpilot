<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence\Doctrine\Type;

use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class NotificationIdType extends UuidType
{
    public const string NAME = 'notification_id';

    protected function identifierClass(): string
    {
        return NotificationId::class;
    }
}
