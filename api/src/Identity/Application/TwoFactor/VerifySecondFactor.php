<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Second step of a sign-in: a 6-digit code from the app, or a recovery code.
 */
final readonly class VerifySecondFactor implements Command
{
    public function __construct(public UserId $userId, public string $code)
    {
    }
}
