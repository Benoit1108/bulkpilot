<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\Profile\UpdateProfile;
use App\Identity\Domain\User\Sex;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<ProfileInput, Account>
 */
final readonly class UpdateProfileProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Account
    {
        $userId = $this->currentUser->id();

        $this->commandBus->dispatch(new UpdateProfile(
            $userId,
            $data->firstName,
            $data->parsedBirthDate(),
            null === $data->sex ? null : Sex::from($data->sex),
            $data->heightCm,
        ));

        return Account::fromView($this->queryBus->ask(new GetAccount($userId)));
    }
}
