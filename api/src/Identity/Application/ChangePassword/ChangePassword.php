<?php

declare(strict_types=1);

namespace App\Identity\Application\ChangePassword;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class ChangePassword implements Command
{
    public function __construct(
        public UserId $userId,
        public UserSessionId $currentSessionId,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
