<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class EmailAlreadyVerified extends Conflict
{
    public static function forUser(\App\Shared\Domain\Identifier\UserId $id): self
    {
        return new self(\sprintf('The email of user %s is already verified.', $id));
    }

    public function errorCode(): string
    {
        return 'identity.email_already_verified';
    }
}
