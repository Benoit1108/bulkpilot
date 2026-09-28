<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\VerifyEmail\VerifyEmail;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;

/**
 * @implements ProcessorInterface<EmailVerification, null>
 */
final readonly class EmailVerificationProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private RateLimits $rateLimits)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->rateLimits->consumeForClient('email_links');

        $this->commandBus->dispatch(new VerifyEmail($data->token));

        return null;
    }
}
