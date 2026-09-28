<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Port\RecoveryCodeGenerator;
use App\Identity\Application\TwoFactor\ConfirmTwoFactor;
use App\Identity\Domain\User\User;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProcessorInterface<CodeInput, RecoveryCodeList>
 */
final readonly class TwoFactorConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
        private RecoveryCodeGenerator $recoveryCodes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RecoveryCodeList
    {
        $codes = $this->recoveryCodes->generate(User::RECOVERY_CODE_COUNT);

        $this->commandBus->dispatch(new ConfirmTwoFactor($this->currentUser->id(), $data->code, $codes));

        return new RecoveryCodeList($codes);
    }
}
