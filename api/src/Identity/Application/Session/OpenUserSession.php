<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class OpenUserSession implements Command
{
    public function __construct(
        public UserSessionId $sessionId,
        public UserId $userId,
        public string $deviceLabel,
    ) {
    }
}
