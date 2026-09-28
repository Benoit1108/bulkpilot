<?php

declare(strict_types=1);

namespace App\Identity\Application\Profile;

use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class UpdateProfileHandler implements CommandHandler
{
    public function __construct(private UserRepository $users, private ClockInterface $clock)
    {
    }

    public function __invoke(UpdateProfile $command): void
    {
        $user = $this->users->get($command->userId);
        $user->updateProfile(new Profile($command->firstName, $this->clock->now(), $command->birthDate, $command->sex, $command->heightCm));
        $this->users->save($user);
    }
}
