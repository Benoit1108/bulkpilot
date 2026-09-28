<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\SignIn\CheckCredentials;
use App\Identity\Presentation\Http\DeviceSignIn;
use App\Identity\Presentation\Http\PendingSignIn;
use App\Identity\Presentation\Http\RateLimits;
use App\Shared\Application\Bus\Query\QueryBus;

/**
 * @implements ProcessorInterface<SignInInput, SignInResult>
 */
final readonly class SignInProcessor implements ProcessorInterface
{
    public function __construct(
        private QueryBus $queryBus,
        private DeviceSignIn $deviceSignIn,
        private PendingSignIn $pendingSignIn,
        private RateLimits $rateLimits,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SignInResult
    {
        $this->rateLimits->consumeForClient('sign_in_ip');
        $this->rateLimits->consumeForClientAnd('sign_in', $data->email);
        $this->rateLimits->consumeForAccount('sign_in_account', $data->email);

        $userId = $this->queryBus->ask(new CheckCredentials($data->email, $data->password));
        $account = $this->queryBus->ask(new GetAccount($userId));

        if ($account->twoFactorEnabled) {
            $this->pendingSignIn->start($userId);

            return SignInResult::secondFactorRequired();
        }

        $this->deviceSignIn->signIn($userId);

        return SignInResult::signedIn(Account::fromView($account));
    }
}
