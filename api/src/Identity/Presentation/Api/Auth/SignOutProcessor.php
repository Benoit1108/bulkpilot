<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Application\Session\RevokeUserSession;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<null, null>
 */
final readonly class SignOutProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private SessionAuthenticator $sessionAuthenticator,
        private CurrentUser $currentUser,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $sessionId = $this->sessionAuthenticator->currentSessionId();
        if (null !== $sessionId) {
            $this->commandBus->dispatch(new RevokeUserSession($this->currentUser->id(), $sessionId));
            $this->sessionAuthenticator->signOut();
        }

        return null;
    }
}
