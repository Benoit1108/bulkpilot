<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * « Supprimer mon compte » (spec §103): with the password (or, for an account without one, a
 * recent sign-in on this device), and the second factor when it is on.
 */
final readonly class ScheduleAccountDeletion implements Command
{
    public function __construct(
        public UserId $userId,
        public string $password,
        public ?string $code,
        /** The device asking: its sign-in proves an account without a password. */
        public ?UserSessionId $sessionId,
    ) {
    }
}
