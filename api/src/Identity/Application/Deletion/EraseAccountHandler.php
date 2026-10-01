<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Application\PersonalData\PersonalData;
use Psr\Clock\ClockInterface;

/**
 * Every context erases what it holds of the user (ADR-0030), the one holding the account last: it
 * is the key the others were erased by. One transaction, the account locked from the start: a
 * sign-in that would cancel the deletion waits, then finds the account gone (or, having cancelled
 * it just before, keeps it: the erasure then does nothing). The files go once it committed,
 * retried in the worker. The erasure is recorded first, so that the activity keeps it,
 * pseudonymised like the rest; the farewell email goes after the commit.
 */
final readonly class EraseAccountHandler implements CommandHandler
{
    /**
     * @param iterable<PersonalData> $sources every context holding personal data
     */
    public function __construct(
        private UserRepository $users,
        private iterable $sources,
        private IdentityMailer $mailer,
        private AuditTrail $audit,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EraseAccount $command): void
    {
        $now = $this->clock->now();
        $user = $this->users->lockForErasure($command->userId);
        if (null === $user || (!$command->restored && !$user->isDueForErasure($now))) {
            return;
        }
        $email = $user->email();
        $firstName = $user->profile()->firstName;
        $this->audit->record($user->id(), 'identity.account_erased', [], 'system', $now);

        $sources = [...$this->sources];
        usort($sources, static fn (PersonalData $a, PersonalData $b): int => $a->erasedLast() <=> $b->erasedLast());
        foreach ($sources as $source) {
            $source->erase($command->userId);
        }

        $this->eventBus->publish(new AccountErased($command->userId, $now));
        if (!$command->restored) {
            $this->mailer->sendAccountErased($email, $firstName);
        }
    }
}
