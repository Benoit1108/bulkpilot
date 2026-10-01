<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Deletion;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\Audit\AuthenticationFailure;
use App\Identity\Application\Audit\RecordAuthenticationFailure;
use App\Identity\Application\Deletion\ScheduleAccountDeletion;
use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<DeletionInput, AccountDeletion>
 */
final readonly class AccountDeletionProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private SessionAuthenticator $sessionAuthenticator,
        private RateLimits $rateLimits,
        private CurrentUser $currentUser,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountDeletion
    {
        $userId = $this->currentUser->id();
        // The same guesses budget as every other password confirmation.
        $this->rateLimits->consumeForAccount('password_confirm', $userId->value);
        try {
            $this->commandBus->dispatch(new ScheduleAccountDeletion($userId, $data->password, $data->code, $this->sessionAuthenticator->currentSessionId()));
        } catch (WrongCurrentPassword|InvalidSecondFactorCode $e) {
            // Someone holding the session is guessing: the owner sees it in their activity.
            $this->commandBus->dispatch(new RecordAuthenticationFailure($userId, AuthenticationFailure::Reauthentication));

            throw $e;
        }
        $dueAt = $this->queryBus->ask(new GetAccount($userId))->deletionDueAt;
        \assert(null !== $dueAt);
        // Its session is revoked with the others: this device is signed out too.
        $this->sessionAuthenticator->signOut();

        return new AccountDeletion($dueAt);
    }
}
