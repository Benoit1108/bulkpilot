<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SymfonySessionAuthenticator implements SessionAuthenticator
{
    public function __construct(private Security $security)
    {
    }

    public function signIn(UserId $userId, UserSessionId $sessionId): void
    {
        // Also migrates the session id (session fixation protection).
        $this->security->login(new SecurityUser($userId->value, $sessionId->value), ProgrammaticAuthenticator::class, 'main');
    }

    public function signOut(): void
    {
        if (null !== $this->security->getUser()) {
            $this->security->logout(validateCsrfToken: false);
        }
    }

    public function currentSessionId(): ?UserSessionId
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? new UserSessionId($user->sessionId) : null;
    }

    public function currentUserId(): ?UserId
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? new UserId($user->userId) : null;
    }
}
