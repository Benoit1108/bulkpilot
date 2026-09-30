<?php

declare(strict_types=1);

namespace App\Notification\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidNotificationSettings extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'notification.invalid_settings';
    }
}
