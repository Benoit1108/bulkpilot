<?php

declare(strict_types=1);

namespace App\Identity\Application\PasswordReset;

use App\Shared\Application\Bus\Command\Command;

final readonly class RequestPasswordReset implements Command
{
    public function __construct(public string $email)
    {
    }
}
