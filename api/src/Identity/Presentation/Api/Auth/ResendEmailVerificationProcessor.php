<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\VerifyEmail\ResendEmailVerification;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<null, null>
 */
final readonly class ResendEmailVerificationProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private CurrentUser $currentUser, private RateLimits $rateLimits)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $userId = $this->currentUser->id();
        $this->rateLimits->consumeForAccount('email_links', 'resend|'.$userId->value);

        $this->commandBus->dispatch(new ResendEmailVerification($userId));

        return null;
    }
}
