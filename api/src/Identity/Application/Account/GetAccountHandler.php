<?php

declare(strict_types=1);

namespace App\Identity\Application\Account;

use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetAccountHandler implements QueryHandler
{
    public function __construct(private UserRepository $users, private PreferencesRepository $preferences)
    {
    }

    public function __invoke(GetAccount $query): AccountView
    {
        $user = $this->users->get($query->userId);
        $profile = $user->profile();

        return new AccountView(
            $user->id()->value,
            $user->email()->value,
            $user->isEmailVerified(),
            $profile->firstName,
            $profile->birthDate,
            $profile->sex,
            $profile->heightCm,
            $user->isTwoFactorEnabled(),
            $user->hasPassword(),
            $this->preferences->of($user->id())?->timeZone()->name ?? TimeZone::DEFAULT,
            $user->deletionDueAt(),
        );
    }
}
