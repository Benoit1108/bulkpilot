<?php

declare(strict_types=1);

namespace App\Notification\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class NotificationNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No notification %s.', $id));
    }

    public function errorCode(): string
    {
        return 'notification.not_found';
    }
}
