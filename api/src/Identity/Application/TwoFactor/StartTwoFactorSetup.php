<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * The caller generates the seed (TotpAuthenticator) because it must show it to the user.
 */
final readonly class StartTwoFactorSetup implements Command
{
    public function __construct(public UserId $userId, public string $password, public string $secret)
    {
    }
}
