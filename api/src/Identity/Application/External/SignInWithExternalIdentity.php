<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Shared\Application\Bus\Command\Command;

final readonly class SignInWithExternalIdentity implements Command
{
    /**
     * @param string $newUserId used only if an account has to be created
     */
    public function __construct(public ExternalProfile $profile, public string $newUserId)
    {
    }
}
