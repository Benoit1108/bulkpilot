<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\Register\RegisterUser;
use App\Identity\Presentation\Http\DeviceSignIn;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProcessorInterface<RegistrationInput, Account>
 */
final readonly class RegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private UuidGenerator $uuids,
        private DeviceSignIn $deviceSignIn,
        private RateLimits $rateLimits,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Account
    {
        $this->rateLimits->consumeForClient('registration');

        $userId = new UserId($this->uuids->generate());
        $this->commandBus->dispatch(new RegisterUser($userId->value, $data->email, $data->password, $data->firstName));
        $this->deviceSignIn->signIn($userId);

        return Account::fromView($this->queryBus->ask(new GetAccount($userId)));
    }
}
