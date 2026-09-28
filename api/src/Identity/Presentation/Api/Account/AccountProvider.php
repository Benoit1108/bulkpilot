<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\Account\GetAccount;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<Account>
 */
final readonly class AccountProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Account
    {
        return Account::fromView($this->queryBus->ask(new GetAccount($this->currentUser->id())));
    }
}
