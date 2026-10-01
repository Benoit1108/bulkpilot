<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Session\RevokeUserSession;
use App\Identity\Domain\Exception\SessionNotFound;
use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Exception\InvalidIdentifier;

/**
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeDeviceSessionProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private CurrentUser $currentUser)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        try {
            $sessionId = new UserSessionId(\is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '');
        } catch (InvalidIdentifier) {
            throw SessionNotFound::create();
        }

        $this->commandBus->dispatch(new RevokeUserSession($this->currentUser->id(), $sessionId, fromDeviceList: true));

        return null;
    }
}
