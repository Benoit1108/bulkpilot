<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class EmailAlreadyUsed extends Conflict
{
    public static function create(): self
    {
        return new self('An account already uses this email address.');
    }

    public function errorCode(): string
    {
        return 'identity.email_already_used';
    }
}
