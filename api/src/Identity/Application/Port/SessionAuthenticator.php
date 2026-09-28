<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Binds the HTTP session of the current request to a signed-in user and device.
 */
interface SessionAuthenticator
{
    public function signIn(UserId $userId, UserSessionId $sessionId): void;

    public function signOut(): void;

    /**
     * The device of the current request, when signed in.
     */
    public function currentSessionId(): ?UserSessionId;

    public function currentUserId(): ?UserId;
}
