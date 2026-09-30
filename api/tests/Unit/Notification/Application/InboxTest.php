<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\Inbox\CountUnread;
use App\Notification\Application\Inbox\CountUnreadHandler;
use App\Notification\Application\Inbox\GetNotification;
use App\Notification\Application\Inbox\GetNotificationHandler;
use App\Notification\Application\Inbox\ListNotifications;
use App\Notification\Application\Inbox\ListNotificationsHandler;
use App\Notification\Application\Inbox\MarkAllRead;
use App\Notification\Application\Inbox\MarkAllReadHandler;
use App\Notification\Application\Inbox\MarkRead;
use App\Notification\Application\Inbox\MarkReadHandler;
use App\Notification\Application\Inbox\NotificationView;
use App\Notification\Application\Settings\ChangeNotificationSettings;
use App\Notification\Application\Settings\ChangeNotificationSettingsHandler;
use App\Notification\Application\Settings\GetNotificationSettings;
use App\Notification\Application\Settings\GetNotificationSettingsHandler;
use App\Notification\Domain\Check\Draft;
use App\Notification\Domain\Exception\InvalidNotificationSettings;
use App\Notification\Domain\Exception\NotificationNotFound;
use App\Notification\Domain\Notification\Category;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Notification\InMemoryNotificationRepository;
use App\Tests\Support\Notification\InMemoryNotificationSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class InboxTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';

    private InMemoryNotificationRepository $notifications;
    private MockClock $clock;
    private UserId $alex;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryNotificationRepository();
        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 09:00:00 UTC'));
        $this->alex = new UserId(self::ALEX);
    }

    private function notify(string $owner, int $n, string $at): NotificationId
    {
        $id = new NotificationId(\sprintf('00000000-0000-7000-8000-%012d', $n));
        $this->notifications->save(Notification::of($id, new UserId($owner), new Draft(Category::Record, 'record:'.$n, 'Nouveau record', 'Bravo.', '/training'), new \DateTimeImmutable($at)));

        return $id;
    }

    /**
     * @return list<string>
     */
    private function page(int $limit, ?\DateTimeImmutable $before = null, ?NotificationId $beforeId = null): array
    {
        return array_map(
            static fn (NotificationView $view): string => substr($view->id, -2),
            new ListNotificationsHandler($this->notifications)(new ListNotifications($this->alex, $limit, $before, $beforeId)),
        );
    }

    public function testTheListIsTheLatestFirstAndPagesOnTimeThenId(): void
    {
        $this->notify(self::ALEX, 1, '2026-09-29 08:00 UTC');
        $this->notify(self::ALEX, 2, '2026-09-30 08:00 UTC');
        $third = $this->notify(self::ALEX, 3, '2026-09-30 08:00 UTC');
        $this->notify(self::SAM, 4, '2026-09-30 08:30 UTC');

        self::assertSame(['03', '02', '01'], $this->page(10));
        self::assertSame(['03'], $this->page(1));
        self::assertSame(['02'], $this->page(1, new \DateTimeImmutable('2026-09-30 08:00 UTC'), $third), 'same time: the id breaks the tie');
        self::assertSame(['01'], $this->page(10, new \DateTimeImmutable('2026-09-30 08:00 UTC')));
        self::assertCount(1, $this->page(0), 'at least one');
    }

    public function testReadingMarksOnceAndCountsTheRest(): void
    {
        $first = $this->notify(self::ALEX, 1, '2026-09-29 08:00 UTC');
        $this->notify(self::ALEX, 2, '2026-09-30 08:00 UTC');
        $this->notify(self::SAM, 3, '2026-09-30 08:00 UTC');
        $count = new CountUnreadHandler($this->notifications);
        self::assertSame(2, $count(new CountUnread($this->alex)));

        new MarkReadHandler($this->notifications, $this->clock)(new MarkRead($first, $this->alex));
        $this->clock->modify('+1 hour');
        new MarkReadHandler($this->notifications, $this->clock)(new MarkRead($first, $this->alex));
        self::assertSame(1, $count(new CountUnread($this->alex)));
        self::assertSame('2026-09-30T09:00:00+00:00', new GetNotificationHandler($this->notifications)(new GetNotification($first, $this->alex))->readAt?->format(\DATE_ATOM), 'the first time is kept');

        new MarkAllReadHandler($this->notifications, $this->clock)(new MarkAllRead($this->alex));
        self::assertSame(0, $count(new CountUnread($this->alex)));
        self::assertSame(1, $count(new CountUnread(new UserId(self::SAM))));
    }

    public function testSomeoneElsesNotificationIsNotFound(): void
    {
        $sams = $this->notify(self::SAM, 1, '2026-09-30 08:00 UTC');

        $this->expectException(NotificationNotFound::class);

        new MarkReadHandler($this->notifications, $this->clock)(new MarkRead($sams, $this->alex));
    }

    public function testSettingsAreEveryCategoryOnOrOffAndTheQuietHours(): void
    {
        $settings = new InMemoryNotificationSettingsRepository();
        $get = new GetNotificationSettingsHandler($settings, $this->clock);
        $defaults = $get(new GetNotificationSettings($this->alex));
        self::assertSame(['low_calories' => true, 'workout_not_started' => true, 'missing_weigh_in' => true, 'goal_reached' => true, 'record' => true, 'weekly_report' => true], $defaults->categories);
        self::assertSame(['23:00', '08:00'], [$defaults->quietFrom, $defaults->quietTo]);

        new ChangeNotificationSettingsHandler($settings, $this->clock)(new ChangeNotificationSettings($this->alex, [Category::Record], '22:30', '07:15'));
        $changed = $get(new GetNotificationSettings($this->alex));
        self::assertSame(['record'], array_keys(array_filter($changed->categories)));
        self::assertSame(['22:30', '07:15'], [$changed->quietFrom, $changed->quietTo]);

        $this->expectException(InvalidNotificationSettings::class);
        new ChangeNotificationSettingsHandler($settings, $this->clock)(new ChangeNotificationSettings($this->alex, [], '7:00', '08:00'));
    }
}
