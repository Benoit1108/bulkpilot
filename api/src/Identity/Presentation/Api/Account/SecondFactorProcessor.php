<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\TwoFactor\VerifySecondFactor;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\SecondFactorNotPending;
use App\Identity\Presentation\Http\DeviceSignIn;
use App\Identity\Presentation\Http\PendingSignIn;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use Psr\Log\LoggerInterface;

/**
 * @implements ProcessorInterface<SecondFactorInput, SignInResult>
 */
final readonly class SecondFactorProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private DeviceSignIn $deviceSignIn,
        private PendingSignIn $pendingSignIn,
        private RateLimits $rateLimits,
        private LoggerInterface $auditLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SignInResult
    {
        $userId = $this->pendingSignIn->userId() ?? throw SecondFactorNotPending::create();
        // Keyed by account: 6-digit codes must not be guessable across many sessions.
        $this->rateLimits->consumeForAccount('second_factor', $userId->value);

        try {
            $this->commandBus->dispatch(new VerifySecondFactor($userId, $data->code));
        } catch (InvalidSecondFactorCode $e) {
            // Someone with the password is guessing codes (or the owner mistyped): keep a trace.
            $this->auditLogger->warning('identity.SecondFactorRejected', ['user_id' => $userId->value]);

            throw $e;
        }
        $this->pendingSignIn->clear();
        $this->deviceSignIn->signIn($userId);

        return SignInResult::signedIn(Account::fromView($this->queryBus->ask(new GetAccount($userId))));
    }
}
