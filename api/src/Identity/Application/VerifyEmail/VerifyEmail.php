<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use App\Shared\Application\Bus\Command\Command;

final readonly class VerifyEmail implements Command
{
    public function __construct(public string $token)
    {
    }
}
