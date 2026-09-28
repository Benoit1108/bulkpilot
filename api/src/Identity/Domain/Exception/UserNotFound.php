<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class UserNotFound extends NotFound
{
    public static function withId(\App\Shared\Domain\Identifier\UserId $id): self
    {
        return new self(\sprintf('User %s does not exist.', $id));
    }

    public function errorCode(): string
    {
        return 'identity.user_not_found';
    }
}
