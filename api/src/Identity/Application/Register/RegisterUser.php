<?php

declare(strict_types=1);

namespace App\Identity\Application\Register;

use App\Shared\Application\Bus\Command\Command;

final readonly class RegisterUser implements Command
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $password,
        public string $firstName,
    ) {
    }
}
