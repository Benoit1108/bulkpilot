<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\PasswordReset\RequestPasswordReset;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;

/**
 * @implements ProcessorInterface<PasswordResetRequest, null>
 */
final readonly class PasswordResetRequestProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private RateLimits $rateLimits)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->rateLimits->consumeForClient('email_links');
        $this->rateLimits->consumeForAccount('email_links', 'reset|'.$data->email);

        $this->commandBus->dispatch(new RequestPasswordReset($data->email));

        return null;
    }
}
