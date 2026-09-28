<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Application\Session\OpenUserSession;
use App\Identity\Application\Session\RevokeUserSession;
use App\Identity\Domain\Exception\SessionNotFound;
use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Records the device, then binds the HTTP session to it (shared by every way of signing in).
 * Signing in again on a device that already had a session replaces it: no orphan device entry.
 */
final readonly class DeviceSignIn
{
    public function __construct(
        private CommandBus $commandBus,
        private SessionAuthenticator $sessionAuthenticator,
        private UuidGenerator $uuids,
        private RequestStack $requestStack,
    ) {
    }

    public function signIn(UserId $userId): void
    {
        $previous = $this->sessionAuthenticator->currentSessionId();
        $previousUser = $this->sessionAuthenticator->currentUserId();
        if (null !== $previous && null !== $previousUser) {
            try {
                $this->commandBus->dispatch(new RevokeUserSession($previousUser, $previous));
            } catch (SessionNotFound) {
                // Already gone: nothing to replace.
            }
        }

        $sessionId = new UserSessionId($this->uuids->generate());
        $this->commandBus->dispatch(new OpenUserSession($sessionId, $userId, DeviceLabel::fromUserAgent($this->requestStack->getMainRequest()?->headers->get('User-Agent'))));
        $this->sessionAuthenticator->signIn($userId, $sessionId);
    }
}
