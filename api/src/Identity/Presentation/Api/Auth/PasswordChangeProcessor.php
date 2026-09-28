<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\ChangePassword\ChangePassword;
use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Application\Security\NotAuthenticated;

/**
 * @implements ProcessorInterface<PasswordChange, null>
 */
final readonly class PasswordChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private RateLimits $rateLimits,
        private CurrentUser $currentUser,
        private SessionAuthenticator $sessionAuthenticator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $sessionId = $this->sessionAuthenticator->currentSessionId() ?? throw new NotAuthenticated('No device session.');
        $this->rateLimits->consumeForAccount('password_confirm', $this->currentUser->id()->value);

        $this->commandBus->dispatch(new ChangePassword($this->currentUser->id(), $sessionId, $data->currentPassword, $data->newPassword));

        return null;
    }
}
