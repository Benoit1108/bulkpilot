<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class RevokeUserSession implements Command
{
    public function __construct(
        public UserId $userId,
        public UserSessionId $sessionId,
        /** Signed out from the list of devices (audited), not by signing out or in on this one. */
        public bool $fromDeviceList = false,
    ) {
    }
}
