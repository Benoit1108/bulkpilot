<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\Port\TotpAuthenticator;
use App\Identity\Application\TwoFactor\StartTwoFactorSetup;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<PasswordConfirmation, TwoFactorSetup>
 */
final readonly class TwoFactorSetupProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private RateLimits $rateLimits,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private TotpAuthenticator $totp,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TwoFactorSetup
    {
        $userId = $this->currentUser->id();
        $this->rateLimits->consumeForAccount('password_confirm', $userId->value);
        $secret = $this->totp->generateSecret();

        $this->commandBus->dispatch(new StartTwoFactorSetup($userId, $data->password, $secret));
        $email = $this->queryBus->ask(new GetAccount($userId))->email;

        return new TwoFactorSetup($secret, $this->totp->provisioningUri($secret, $email));
    }
}
