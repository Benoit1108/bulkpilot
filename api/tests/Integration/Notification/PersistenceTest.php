<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Notification\Domain\Check\CheckLog;
use App\Notification\Domain\Check\Draft;
use App\Notification\Domain\Notification\Category;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationId;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Notification\Domain\Settings\NotificationSettings;
use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Notification\Domain\Settings\QuietHours;
use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string ALEX = '0199a0b1-7c3e-7d4a-9b2f-00000000d001';
    private const string SAM = '0199a0b1-7c3e-7d4a-9b2f-00000000d002';

    private EntityManagerInterface $entityManager;
    private NotificationRepository $notifications;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->notifications = self::getContainer()->get(NotificationRepository::class);
    }

    private function notify(string $key, string $at, string $owner = self::ALEX): Notification
    {
        $notification = Notification::of(
            new NotificationId(\sprintf('0199a0b1-7c3e-7d4a-9b2f-%012d', ++$this->ids)),
            new UserId($owner),
            new Draft(Category::Record, $key, 'Nouveau record', 'Tu as battu un record.', '/training/seances/1'),
            new \DateTimeImmutable($at),
        );
        $this->notifications->save($notification);

        return $notification;
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private static function keys(array $notifications): array
    {
        return array_map(static fn (Notification $notification): string => $notification->dedupKey(), $notifications);
    }

    public function testANotificationRoundTrips(): void
    {
        $made = $this->notify('record:1', '2026-09-30 08:00:00+00:00');
        $this->flushAndClear();

        $loaded = $this->notifications->getOwnedBy($made->id(), new UserId(self::ALEX));

        self::assertNotSame($made, $loaded);
        self::assertSame([Category::Record, 'record:1', 'Nouveau record', 'Tu as battu un record.', '/training/seances/1', null], [$loaded->category(), $loaded->dedupKey(), $loaded->title(), $loaded->body(), $loaded->link(), $loaded->readAt()]);
        self::assertSame('2026-09-30T08:00:00+00:00', $loaded->createdAt()->format(\DATE_ATOM));
    }

    public function testTheLatestComeFirstAndTheCursorBreaksSameSecondTiesById(): void
    {
        $this->notify('a', '2026-09-29 08:00:00+00:00');
        $this->notify('b', '2026-09-30 08:00:00+00:00');
        $third = $this->notify('c', '2026-09-30 08:00:00+00:00');
        $this->notify('d', '2026-09-30 09:00:00+00:00', self::SAM);
        $this->flushAndClear();
        $alex = new UserId(self::ALEX);

        self::assertSame(['c', 'b', 'a'], self::keys($this->notifications->latestOf($alex, 10)));
        self::assertSame(['b', 'a'], self::keys($this->notifications->latestOf($alex, 10, $third->createdAt(), $third->id())));
        self::assertSame(['a'], self::keys($this->notifications->latestOf($alex, 10, $third->createdAt())));
        self::assertSame(['c'], self::keys($this->notifications->latestOf($alex, 1)));
    }

    public function testKnownKeysUnreadCountAndMarkingAllRead(): void
    {
        $this->notify('record:1', '2026-09-30 08:00:00+00:00');
        $this->notify('record:2', '2026-09-30 08:00:00+00:00');
        $this->notify('record:1', '2026-09-30 08:00:00+00:00', self::SAM);
        $this->flushAndClear();
        $alex = new UserId(self::ALEX);

        self::assertSame(['record:2'], $this->notifications->existingKeys($alex, ['record:2', 'record:3']));
        self::assertSame([], $this->notifications->existingKeys($alex, []));
        self::assertSame(2, $this->notifications->countUnread($alex));

        $this->notifications->markAllRead($alex, new \DateTimeImmutable('2026-09-30 10:00:00+00:00'));
        $this->entityManager->clear();
        self::assertSame(0, $this->notifications->countUnread($alex));
        self::assertSame(1, $this->notifications->countUnread(new UserId(self::SAM)));
        self::assertSame('2026-09-30T10:00:00+00:00', $this->notifications->latestOf($alex, 1)[0]->readAt()?->format(\DATE_ATOM));
    }

    public function testASubjectIsNotifiedOnceEvenWhenTwoChecksRace(): void
    {
        $this->notify('record:1', '2026-09-30 08:00:00+00:00');
        $this->notify('record:1', '2026-09-30 08:00:00+00:00');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testSettingsRoundTripAndTwoWritersAreRefused(): void
    {
        $settings = self::getContainer()->get(NotificationSettingsRepository::class);
        $alex = new UserId(self::ALEX);
        $now = new \DateTimeImmutable('2026-09-30 08:00:00+00:00');
        self::assertNull($this->entityManager->find(NotificationSettings::class, $alex), 'defaults are not saved');

        $mine = $settings->ofOrDefault($alex, $now);
        $mine->change([Category::Record], new QuietHours('22:00', '07:00'), $now);
        $settings->save($mine);
        $this->flushAndClear();

        $loaded = $settings->ofOrDefault($alex, $now);
        self::assertTrue($loaded->isEnabled(Category::Record));
        self::assertFalse($loaded->isEnabled(Category::WeeklyReport));
        self::assertSame(['22:00', '07:00'], [$loaded->quietHours()?->from, $loaded->quietHours()?->to]);

        $loaded->change(Category::cases(), null, $now);
        $settings->save($loaded);
        $this->flushAndClear();
        self::assertNull($settings->ofOrDefault($alex, $now)->quietHours(), 'no quiet hours, kept as such');

        $again = $settings->ofOrDefault($alex, $now);
        $this->entityManager->getConnection()->executeStatement('UPDATE notification_settings SET version = version + 1 WHERE owner_id = ?', [self::ALEX]);
        $again->change([], null, $now->modify('+10 minutes'));
        $this->expectException(OptimisticLockException::class);
        $this->entityManager->flush();
    }

    public function testAnAccountTakesItsTurnAtMostEveryFewMinutes(): void
    {
        $log = self::getContainer()->get(CheckLog::class);
        $now = new \DateTimeImmutable('2026-09-30 18:30:00+00:00');
        $alex = new UserId(self::ALEX);

        self::assertTrue($log->takeTurn($alex, $now), 'the first check');
        self::assertFalse($log->takeTurn($alex, $now->modify('+4 minutes')), 'too soon');
        self::assertTrue($log->takeTurn(new UserId(self::SAM), $now->modify('+4 minutes')), 'Sam has a turn of his own');
        self::assertTrue($log->takeTurn($alex, $now->modify('+5 minutes')));
        self::assertSame(
            '2026-09-30 18:35:00+00',
            $this->entityManager->getConnection()->fetchOne('SELECT checked_at FROM notification_checks WHERE owner_id = ?', [self::ALEX]),
        );
    }

    public function testTheClockOfOneAccountIsReadFromItsPreferences(): void
    {
        $users = self::getContainer()->get(UserRepository::class);
        $user = User::register(new UserId(self::ALEX), new EmailAddress('alex@example.com'), new HashedPassword('hash'), new Profile('Alex', new \DateTimeImmutable('2026-09-28'), null, null, null), new \DateTimeImmutable('2026-09-28 10:00:00'));
        $users->save($user);
        $preferences = Preferences::defaults(new UserId(self::ALEX), new \DateTimeImmutable('2026-09-28 10:00:00'));
        $preferences->moveToTimeZone(new TimeZone('Asia/Tokyo'), new \DateTimeImmutable('2026-09-28 10:00:00'));
        self::getContainer()->get(PreferencesRepository::class)->save($preferences);
        $this->flushAndClear();
        $accounts = self::getContainer()->get(Accounts::class);

        self::assertSame('Asia/Tokyo', $accounts->clockOf(new UserId(self::ALEX))?->timeZone);
        self::assertNull($accounts->clockOf(new UserId(self::SAM)));

        // A zone the time zone database dropped since: the default, and nothing stops.
        $this->entityManager->getConnection()->executeStatement("UPDATE user_preferences SET time_zone = 'Mars/Olympus' WHERE owner_id = ?", [self::ALEX]);
        self::assertSame('Europe/Paris', $accounts->clockOf(new UserId(self::ALEX))?->timeZone);
        self::assertContains(self::ALEX, array_map(static fn (AccountClock $clock): string => $clock->userId->value, $accounts->all()));
    }
}
