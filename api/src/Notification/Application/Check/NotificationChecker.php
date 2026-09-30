<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Notification\Domain\Check\CheckLog;
use App\Notification\Domain\Check\Checks;
use App\Notification\Domain\Check\Draft;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationId;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Clock\ClockInterface;

/**
 * Runs the rules for an account and keeps what is new: a notification per key, ever (spec §77).
 * Run by the schedule and when the app opens. Nothing is read during the quiet hours or with every
 * category off; a check a few minutes after the last one (or during it) does nothing.
 */
final readonly class NotificationChecker
{
    public function __construct(
        private NotificationSettingsRepository $settings,
        private NotificationRepository $notifications,
        private CheckLog $checks,
        private SituationReader $situations,
        private EventBus $eventBus,
        private UuidGenerator $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function check(AccountClock $account): void
    {
        $now = $this->clock->now();
        $settings = $this->settings->ofOrDefault($account->userId, $now);
        if ($settings->allOff() || $settings->isQuietAt($account->now($now)) || !$this->checks->takeTurn($account->userId, $now)) {
            return;
        }

        $drafts = Checks::drafts($this->situations->at($account, $now), $settings);
        $known = $this->notifications->existingKeys($account->userId, array_map(static fn (Draft $draft): string => $draft->key, $drafts));
        foreach ($drafts as $draft) {
            if (\in_array($draft->key, $known, true)) {
                continue;
            }
            $notification = Notification::of(new NotificationId($this->uuids->generate()), $account->userId, $draft, $now);
            $this->notifications->save($notification);
            $this->eventBus->publish(...$notification->pullDomainEvents());
        }
    }
}
