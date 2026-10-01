<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\Check\CheckAccount;
use App\Notification\Application\Check\CheckAccountHandler;
use App\Notification\Application\Check\CheckNotifications;
use App\Notification\Application\Check\CheckNotificationsHandler;
use App\Notification\Application\Check\NotificationChecker;
use App\Notification\Application\Check\RunScheduledChecks;
use App\Notification\Application\Check\RunScheduledChecksHandler;
use App\Notification\Application\Check\SituationReader;
use App\Notification\Application\Push\PushNotification;
use App\Notification\Domain\Notification\Category;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationCreated;
use App\Notification\Domain\Settings\QuietHours;
use App\Shared\Application\Notifying\DayEating;
use App\Shared\Application\Notifying\DayTraining;
use App\Shared\Application\Notifying\DayWeighing;
use App\Shared\Application\Notifying\RecordWorkout;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Notification\FakeCoachReports;
use App\Tests\Support\Notification\FakeEatingReadings;
use App\Tests\Support\Notification\FakeTrainingActivity;
use App\Tests\Support\Notification\FakeWeighingReadings;
use App\Tests\Support\Notification\InMemoryCheckLog;
use App\Tests\Support\Notification\InMemoryNotificationRepository;
use App\Tests\Support\Notification\InMemoryNotificationSettingsRepository;
use App\Tests\Support\Notification\SpyPushSender;
use App\Tests\Support\Shared\FakeAccounts;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Tests\Support\Shared\SpyCommandBus;
use App\Tests\Support\Shared\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

/**
 * The checks of an account (ADR-0029): read in its time zone, one notification per subject,
 * nothing new a few minutes after the last run, nothing read when nothing could be told, and a
 * push only for a notification that was committed. The server's clock is UTC: 18:30 UTC is 20:30
 * in Paris on Wednesday 2026-09-30.
 */
final class CheckTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';

    private InMemoryNotificationRepository $notifications;
    private InMemoryNotificationSettingsRepository $settings;
    private FakeEatingReadings $eating;
    private FakeWeighingReadings $weighing;
    private FakeTrainingActivity $training;
    private FakeCoachReports $reports;
    private SpyPushSender $push;
    private SpyEventBus $events;
    private InMemoryCheckLog $checks;
    private FakeAccounts $accounts;
    private MockClock $clock;
    private SequentialUuidGenerator $uuids;
    private UserId $alex;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryNotificationRepository();
        $this->settings = new InMemoryNotificationSettingsRepository();
        $this->eating = new FakeEatingReadings();
        $this->weighing = new FakeWeighingReadings();
        $this->training = new FakeTrainingActivity();
        $this->reports = new FakeCoachReports();
        $this->push = new SpyPushSender();
        $this->events = new SpyEventBus();
        $this->checks = new InMemoryCheckLog();
        $this->accounts = new FakeAccounts();
        $this->accounts->add(self::ALEX, 'Europe/Paris');
        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 18:30:00', new \DateTimeZone('UTC')));
        $this->alex = new UserId(self::ALEX);
        $this->uuids = new SequentialUuidGenerator();
        $this->eating->days[self::ALEX] = new DayEating(1000.0, 2350, new \DateTimeImmutable('2026-09-30 10:30:00 UTC'));
    }

    private function checker(): NotificationChecker
    {
        return new NotificationChecker(
            $this->settings,
            $this->notifications,
            $this->checks,
            new SituationReader($this->eating, $this->weighing, $this->training, $this->reports),
            $this->events,
            $this->uuids,
            $this->clock,
        );
    }

    private function check(): void
    {
        new CheckNotificationsHandler($this->checker(), $this->accounts)(new CheckNotifications($this->alex));
    }

    /** What the worker does with the events once the check committed. */
    private function deliver(): void
    {
        $push = new PushNotification($this->notifications, $this->push);
        foreach ($this->events->published as $event) {
            if ($event instanceof NotificationCreated) {
                $push($event);
            }
        }
        $this->events->published = [];
    }

    /**
     * @return list<string>
     */
    private function keys(string $userId = self::ALEX): array
    {
        return array_map(static fn (Notification $notification): string => $notification->dedupKey(), $this->notifications->of(new UserId($userId)));
    }

    public function testTheChecksReadTheUsersDayInTheirTimeZone(): void
    {
        $this->check();

        self::assertSame(['low_calories:2026-09-30'], $this->keys());
        self::assertSame(['2026-09-30'], $this->eating->asked);
        [$dayStart, $since] = $this->training->asked[0];
        self::assertSame('2026-09-29T22:00:00+00:00', $dayStart->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), 'midnight in Paris');
        self::assertSame('2026-09-28T18:30:00+00:00', $since->format(\DATE_ATOM), 'records of the last 48 hours');
        self::assertSame([NotificationCreated::class], $this->events->publishedClasses());
        self::assertSame([], $this->push->sent, 'nothing is pushed before the commit');
        $this->deliver();
        self::assertCount(1, $this->push->sent);
        self::assertSame($this->clock->now()->format(\DATE_ATOM), $this->checks->checkedAt[self::ALEX]->format(\DATE_ATOM));
    }

    public function testAfterMidnightInNewYorkItIsStillTheDayBeforeThere(): void
    {
        $this->accounts->accounts = [];
        $this->accounts->add(self::ALEX, 'America/New_York');
        $this->clock = new MockClock(new \DateTimeImmutable('2026-10-01 00:30:00 UTC'));

        $this->check();

        self::assertSame(['low_calories:2026-09-30'], $this->keys(), '20:30 on the 30th in New York');
        self::assertSame(['2026-09-30'], $this->eating->asked);
    }

    public function testInTokyoItIsAlreadyTomorrow(): void
    {
        $this->accounts->accounts = [];
        $this->accounts->add(self::ALEX, 'Asia/Tokyo');
        $settings = $this->settings->ofOrDefault($this->alex, $this->clock->now());
        $settings->change(Category::cases(), null, $this->clock->now());
        $this->settings->save($settings);

        $this->check();

        self::assertSame(['2026-10-01'], $this->eating->asked, '03:30 on the 1st in Tokyo');
        self::assertSame([], $this->keys(), '03:30 is not the evening');
    }

    public function testAtTheSameMomentAnotherTimeZoneHasNoEveningYet(): void
    {
        $this->accounts->accounts = [];
        $this->accounts->add(self::ALEX, 'America/New_York');

        $this->check();

        self::assertSame([], $this->keys(), '14:30 in New York');
        self::assertSame(['2026-09-30'], $this->eating->asked);
    }

    public function testAnAccountDeletedMeanwhileIsNotChecked(): void
    {
        $this->accounts->accounts = [];

        $this->check();

        self::assertSame([], $this->keys());
        self::assertSame([], $this->eating->asked);
    }

    public function testASubjectIsToldOnceAndTheChecksRestAFewMinutes(): void
    {
        $this->check();
        $this->clock->modify('+2 minutes');
        $this->weighing->days[self::ALEX] = new DayWeighing(false, 5, []);
        $this->check();
        self::assertSame(['low_calories:2026-09-30'], $this->keys(), 'checked 2 minutes ago: nothing runs');

        $this->clock->modify('+5 minutes');
        $this->check();
        self::assertSame(['low_calories:2026-09-30', 'missing_weigh_in:2026-09-30'], $this->keys(), 'the calories are not told twice');
        $this->deliver();
        self::assertCount(2, $this->push->sent);
    }

    public function testARetriedCheckMakesNothingTwice(): void
    {
        new CheckAccountHandler($this->checker(), $this->accounts)(new CheckAccount($this->alex, 'Europe/Paris'));
        $this->deliver();
        // The worker gets the same message again (a retry, or a second worker), minutes later.
        $this->clock->modify('+10 minutes');
        new CheckAccountHandler($this->checker(), $this->accounts)(new CheckAccount($this->alex, 'Europe/Paris'));
        $this->deliver();

        self::assertSame(['low_calories:2026-09-30'], $this->keys());
        self::assertCount(1, $this->push->sent);
    }

    public function testEveryNewWorkoutWithRecordsIsToldAndTurnedOffCategoriesAreNot(): void
    {
        $settings = $this->settings->ofOrDefault($this->alex, $this->clock->now());
        $settings->change([Category::Record], QuietHours::default(), $this->clock->now());
        $this->settings->save($settings);
        $this->training->days[self::ALEX] = new DayTraining(3, 1, true, [new RecordWorkout('w-1', 2)]);

        $this->check();

        self::assertSame(['record:w-1'], $this->keys());
        self::assertSame('2 nouveaux records', $this->notifications->of($this->alex)[0]->title());
    }

    public function testNothingIsReadDuringTheQuietHoursNorWithEveryCategoryOff(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 21:30:00 UTC'));
        $this->check();
        self::assertSame([], $this->keys(), '23:30 in Paris');
        self::assertSame([], $this->eating->asked, 'nothing read');
        self::assertSame([], $this->checks->checkedAt, 'the turn is not taken: the check at 08:00 runs');

        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 18:30:00 UTC'));
        $settings = $this->settings->ofOrDefault($this->alex, $this->clock->now());
        $settings->change([], QuietHours::default(), $this->clock->now());
        $this->settings->save($settings);
        $this->check();
        self::assertSame([], $this->eating->asked);
    }

    public function testWithoutQuietHoursTheNightIsChecked(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 21:30:00 UTC'));
        $settings = $this->settings->ofOrDefault($this->alex, $this->clock->now());
        $settings->change(Category::cases(), null, $this->clock->now());
        $this->settings->save($settings);

        $this->check();

        self::assertSame(['low_calories:2026-09-30'], $this->keys(), '23:30 in Paris, no quiet hours');
    }

    public function testTheScheduleSendsOneCheckPerAccountToTheWorker(): void
    {
        $this->accounts->add(self::SAM, 'Asia/Tokyo');
        $bus = new SpyCommandBus();

        new RunScheduledChecksHandler($this->accounts, $bus, new NullLogger())(new RunScheduledChecks());

        self::assertSame(
            [[self::ALEX, 'Europe/Paris'], [self::SAM, 'Asia/Tokyo']],
            array_map(static fn (object $check): array => $check instanceof CheckAccount ? [$check->userId->value, $check->timeZone] : [], $bus->dispatched),
        );
    }

    public function testAScheduledCheckRunsInTheZoneItCarries(): void
    {
        new CheckAccountHandler($this->checker(), $this->accounts)(new CheckAccount($this->alex, 'Europe/Paris'));

        self::assertSame(['low_calories:2026-09-30'], $this->keys());
        self::assertSame([], $this->keys(self::SAM));
    }
}
