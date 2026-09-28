<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use App\Identity\Application\Token\OneTimeLinks;
use App\Identity\Domain\Token\TokenPurpose;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class VerifyEmailHandler implements CommandHandler
{
    public function __construct(
        private OneTimeLinks $links,
        private UserRepository $users,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyEmail $command): void
    {
        $now = $this->clock->now();
        $user = $this->users->get($this->links->redeem(TokenPurpose::EmailVerification, $command->token, $now));

        if (!$user->isEmailVerified()) {
            $user->verifyEmail($now);
            $this->users->save($user);
        }

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
