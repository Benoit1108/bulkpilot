<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class DisableTwoFactor implements Command
{
    public function __construct(public UserId $userId, public string $password)
    {
    }
}
