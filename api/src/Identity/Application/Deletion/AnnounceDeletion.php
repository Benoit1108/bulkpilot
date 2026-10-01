<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\AccountDeletionCancelled;
use App\Identity\Domain\User\AccountDeletionScheduled;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * An email at each step of the deletion (spec §103) and a line in the account's activity. The
 * date of the erasure is written in the user's time zone.
 */
final readonly class AnnounceDeletion implements DomainEventSubscriber
{
    public function __construct(
        private UserRepository $users,
        private PreferencesRepository $preferences,
        private IdentityMailer $mailer,
        private AuditTrail $audit,
    ) {
    }

    public function __invoke(AccountDeletionScheduled|AccountDeletionCancelled $event): void
    {
        $user = $this->users->find($event->userId);
        if (null === $user) {
            return;
        }

        $zone = new \DateTimeZone(TimeZone::orDefault($this->preferences->of($user->id())?->timeZone()->name ?? TimeZone::DEFAULT)->name);
        if ($event instanceof AccountDeletionScheduled) {
            $dueAt = $event->dueAt->setTimezone($zone);
            $this->mailer->sendDeletionScheduled($user, $dueAt);
            $this->audit->record($user->id(), 'identity.deletion_scheduled', ['due_on' => $dueAt->format('Y-m-d')], 'user', $event->occurredOn());

            return;
        }
        $this->mailer->sendDeletionCancelled($user, $event->occurredOn()->setTimezone($zone));
        $this->audit->record($user->id(), 'identity.deletion_cancelled', [], 'user', $event->occurredOn());
    }
}
