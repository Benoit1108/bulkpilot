<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Port\RecoveryCodeGenerator;
use App\Identity\Application\TwoFactor\RegenerateRecoveryCodes;
use App\Identity\Domain\User\User;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<PasswordConfirmation, RecoveryCodeList>
 */
final readonly class RecoveryCodesProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private RateLimits $rateLimits,
        private CurrentUser $currentUser,
        private RecoveryCodeGenerator $recoveryCodes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RecoveryCodeList
    {
        $this->rateLimits->consumeForAccount('password_confirm', $this->currentUser->id()->value);
        $codes = $this->recoveryCodes->generate(User::RECOVERY_CODE_COUNT);

        $this->commandBus->dispatch(new RegenerateRecoveryCodes($this->currentUser->id(), $data->password, $codes));

        return new RecoveryCodeList($codes);
    }
}
