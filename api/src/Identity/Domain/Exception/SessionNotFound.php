<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class SessionNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No such active session for this user.');
    }

    public function errorCode(): string
    {
        return 'identity.session_not_found';
    }
}
