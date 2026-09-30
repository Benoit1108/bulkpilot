<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Accounts\IdentityAccounts;
use App\Identity\Application\Preferences\ArrangeDashboard;
use App\Identity\Application\Preferences\ArrangeDashboardHandler;
use App\Identity\Application\Preferences\ChangeTimeZone;
use App\Identity\Application\Preferences\ChangeTimeZoneHandler;
use App\Identity\Application\Preferences\GetDashboardLayout;
use App\Identity\Application\Preferences\GetDashboardLayoutHandler;
use App\Identity\Application\Preferences\WidgetView;
use App\Identity\Domain\Dashboard\DashboardLayout;
use App\Identity\Domain\Dashboard\Widget;
use App\Identity\Domain\Exception\InvalidDashboardLayout;
use App\Identity\Domain\Exception\InvalidTimeZone;
use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Identity\InMemoryPreferencesRepository;
use App\Tests\Support\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PreferencesTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';

    public function testATimeZoneIsAnIanaOne(): void
    {
        self::assertSame('America/Montreal', new TimeZone('America/Montreal')->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTimeZones(): iterable
    {
        yield 'unknown' => ['Mars/Olympus'];
        yield 'wrong case' => ['europe/paris'];
        yield 'an offset' => ['+02:00'];
        yield 'empty' => [''];
    }

    #[DataProvider('notTimeZones')]
    public function testAnythingElseIsRefused(string $name): void
    {
        $this->expectException(InvalidTimeZone::class);
        new TimeZone($name);
    }

    public function testAUserIsInTheDefaultZoneUntilADeviceSaysOtherwise(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 10:00');
        $preferences = Preferences::defaults(new UserId(self::ALEX), $now);
        self::assertSame(TimeZone::DEFAULT, $preferences->timeZone()->name);

        $preferences->moveToTimeZone(new TimeZone('Asia/Tokyo'), $now);
        self::assertSame('Asia/Tokyo', $preferences->timeZone()->name);
    }

    public function testTheTimeZoneAndTheLayoutAreKeptTogether(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(self::user(self::ALEX));
        $preferences = new InMemoryPreferencesRepository($users);
        $clock = new MockClock('2026-09-30 10:00');
        $arranged = array_reverse(array_map(static fn (Widget $w): array => ['key' => $w->value, 'visible' => true], Widget::cases()));

        new ChangeTimeZoneHandler($preferences, $clock)(new ChangeTimeZone(new UserId(self::ALEX), 'Asia/Tokyo'));
        new ArrangeDashboardHandler($preferences, $clock)(new ArrangeDashboard(new UserId(self::ALEX), $arranged));

        $alex = $preferences->of(new UserId(self::ALEX));
        self::assertNotNull($alex);
        self::assertSame('Asia/Tokyo', $alex->timeZone()->name, 'arranging kept the zone');
        self::assertSame($arranged, $alex->dashboardLayout()->toArray());
    }

    public function testTheDefaultDashboardShowsEveryWidgetInOrder(): void
    {
        $layout = $this->layout(new InMemoryPreferencesRepository(), self::ALEX);

        self::assertSame(self::keys(Widget::cases()), array_map(static fn (WidgetView $widget): string => $widget->key, $layout));
        self::assertSame([true], array_values(array_unique(array_map(static fn (WidgetView $widget): bool => $widget->visible, $layout))));
    }

    public function testAnArrangedDashboardIsKeptAsArrangedApartFromTheAccount(): void
    {
        $arranged = array_reverse(array_map(static fn (Widget $w): array => ['key' => $w->value, 'visible' => Widget::Meals !== $w], Widget::cases()));
        $dashboards = new InMemoryPreferencesRepository();
        $arrange = new ArrangeDashboardHandler($dashboards, new MockClock('2026-09-30 10:00'));

        $arrange(new ArrangeDashboard(new UserId(self::ALEX), $arranged));
        $arrange(new ArrangeDashboard(new UserId(self::ALEX), $arranged));

        self::assertSame($arranged, array_map(static fn (WidgetView $widget): array => ['key' => $widget->key, 'visible' => $widget->visible], $this->layout($dashboards, self::ALEX)));
        self::assertSame(self::keys(Widget::cases()), array_map(static fn (WidgetView $widget): string => $widget->key, $this->layout($dashboards, self::SAM)), 'Sam\'s is still the default');
    }

    /**
     * @return list<WidgetView>
     */
    private function layout(InMemoryPreferencesRepository $dashboards, string $userId): array
    {
        return new GetDashboardLayoutHandler($dashboards)(new GetDashboardLayout(new UserId($userId)));
    }

    /**
     * @return iterable<string, array{list<array{key: string, visible: bool}>}>
     */
    public static function badLayouts(): iterable
    {
        $all = array_map(static fn (Widget $w): array => ['key' => $w->value, 'visible' => true], Widget::cases());
        yield 'one missing' => [\array_slice($all, 1)];
        yield 'one twice' => [[...$all, $all[0]]];
        yield 'an unknown one' => [[...\array_slice($all, 1), ['key' => 'horoscope', 'visible' => true]]];
        yield 'empty' => [[]];
    }

    /**
     * @param list<array{key: string, visible: bool}> $widgets
     */
    #[DataProvider('badLayouts')]
    public function testEveryWidgetIsPlacedExactlyOnce(array $widgets): void
    {
        $this->expectException(InvalidDashboardLayout::class);
        DashboardLayout::arranged($widgets);
    }

    public function testASavedLayoutSurvivesWidgetsAddedOrRemovedSince(): void
    {
        $stored = [['key' => 'coach', 'visible' => false], ['key' => 'horoscope', 'visible' => true], ['key' => 'weight', 'visible' => true], ['key' => 'coach', 'visible' => true]];

        $layout = DashboardLayout::stored($stored)->toArray();

        self::assertSame(['key' => 'coach', 'visible' => false], $layout[0], 'kept, the first placement wins');
        self::assertSame(['key' => 'weight', 'visible' => true], $layout[1], 'a removed widget is dropped');
        self::assertCount(\count(Widget::cases()), $layout, 'the widgets added since are appended');
        self::assertSame(['nutrition', 'meals', 'workout', 'goal', 'records', 'report'], array_column(\array_slice($layout, 2), 'key'));
    }

    public function testScheduledWorkListsEveryAccountWithItsZone(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(self::user(self::ALEX));
        $users->save(self::user(self::SAM, 'sam@example.com'));
        $preferences = new InMemoryPreferencesRepository($users);
        new ChangeTimeZoneHandler($preferences, new MockClock('2026-09-30 10:00'))(new ChangeTimeZone(new UserId(self::ALEX), 'America/Montreal'));

        $accounts = new IdentityAccounts($preferences)->all();

        self::assertSame(
            [[self::ALEX, 'America/Montreal'], [self::SAM, 'Europe/Paris']],
            array_map(static fn (AccountClock $account): array => [$account->userId->value, $account->timeZone], $accounts),
        );
        self::assertSame('2026-09-30 04:30', $accounts[0]->now(new \DateTimeImmutable('2026-09-30 08:30', new \DateTimeZone('UTC')))->format('Y-m-d H:i'));
    }

    public function testOneAccountsClockIsReadAlone(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(self::user(self::ALEX));
        $users->save(self::user(self::SAM, 'sam@example.com'));
        $preferences = new InMemoryPreferencesRepository($users);
        new ChangeTimeZoneHandler($preferences, new MockClock('2026-09-30 10:00'))(new ChangeTimeZone(new UserId(self::ALEX), 'Asia/Tokyo'));
        $accounts = new IdentityAccounts($preferences);

        self::assertSame('Asia/Tokyo', $accounts->clockOf(new UserId(self::ALEX))?->timeZone);
        self::assertNull($accounts->clockOf(new UserId('00000000-0000-7000-8000-0000000000de')), 'no such account');
    }

    public function testAZoneTheDatabaseNoLongerKnowsGivesTheDefault(): void
    {
        self::assertSame(TimeZone::DEFAULT, TimeZone::orDefault('Mars/Olympus')->name);
        self::assertSame('Asia/Tokyo', TimeZone::orDefault('Asia/Tokyo')->name);
    }

    /**
     * @param list<Widget> $widgets
     *
     * @return list<string>
     */
    private static function keys(array $widgets): array
    {
        return array_map(static fn (Widget $w): string => $w->value, $widgets);
    }

    private static function user(string $id, string $email = 'alex@example.com'): User
    {
        return User::register(new UserId($id), new EmailAddress($email), new HashedPassword('hash'), new Profile('Alex', new \DateTimeImmutable('2026-09-28'), null, null, null), new \DateTimeImmutable('2026-09-28 10:00:00'));
    }
}
