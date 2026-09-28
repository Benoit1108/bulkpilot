<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\TwoFactor\DisableTwoFactor;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<PasswordConfirmation, null>
 */
final readonly class TwoFactorDisableProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private RateLimits $rateLimits, private CurrentUser $currentUser)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->rateLimits->consumeForAccount('password_confirm', $this->currentUser->id()->value);
        $this->commandBus->dispatch(new DisableTwoFactor($this->currentUser->id(), $data->password));

        return null;
    }
}
