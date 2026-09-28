<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\PasswordReset\ResetPassword;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;

/**
 * @implements ProcessorInterface<PasswordReset, null>
 */
final readonly class PasswordResetProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private RateLimits $rateLimits)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->rateLimits->consumeForClient('email_links');

        $this->commandBus->dispatch(new ResetPassword($data->token, $data->newPassword));

        return null;
    }
}
