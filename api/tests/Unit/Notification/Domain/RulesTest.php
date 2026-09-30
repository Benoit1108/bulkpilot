<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\Check\Checks;
use App\Notification\Domain\Check\Draft;
use App\Notification\Domain\Check\GoalReachedRule;
use App\Notification\Domain\Check\GoalStep;
use App\Notification\Domain\Check\LowCaloriesRule;
use App\Notification\Domain\Check\MissingWeighInRule;
use App\Notification\Domain\Check\RecordRule;
use App\Notification\Domain\Check\RecordSession;
use App\Notification\Domain\Check\Situation;
use App\Notification\Domain\Check\WeeklyReportRule;
use App\Notification\Domain\Check\WorkoutNotStartedRule;
use App\Notification\Domain\Notification\Category;
use App\Notification\Domain\Settings\NotificationSettings;
use App\Notification\Domain\Settings\QuietHours;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rules behind the notifications (spec §75–77), in the user's local time: Wednesday
 * 2026-09-30 unless said otherwise.
 */
final class RulesTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    /**
     * @param array{now?: \DateTimeImmutable, eatenKcal?: float, targetKcal?: ?int, lastLoggedAt?: ?\DateTimeImmutable, weighedToday?: bool, weighInsLastWeek?: int, reachedSteps?: list<GoalStep>, plannedPerWeek?: ?int, sessionsThisWeek?: int, startedToday?: bool, recordSessions?: list<RecordSession>, latestReportWeek?: ?\DateTimeImmutable} $with
     */
    private static function at(string $time, array $with = []): Situation
    {
        return new Situation(
            $with['now'] ?? new \DateTimeImmutable('2026-09-30 '.$time, new \DateTimeZone('Europe/Paris')),
            $with['eatenKcal'] ?? 0.0,
            $with['targetKcal'] ?? null,
            $with['lastLoggedAt'] ?? null,
            $with['weighedToday'] ?? false,
            $with['weighInsLastWeek'] ?? 0,
            $with['reachedSteps'] ?? [],
            $with['plannedPerWeek'] ?? null,
            $with['sessionsThisWeek'] ?? 0,
            $with['startedToday'] ?? false,
            $with['recordSessions'] ?? [],
            $with['latestReportWeek'] ?? null,
        );
    }

    private static function lunch(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-30 12:30', new \DateTimeZone('Europe/Paris'));
    }

    public function testVeryLittleEatenInTheEveningIsTold(): void
    {
        $drafts = new LowCaloriesRule()->evaluate(self::at('20:30', ['eatenKcal' => 1240.0, 'targetKcal' => 2350, 'lastLoggedAt' => self::lunch()]));

        self::assertCount(1, $drafts);
        self::assertSame(Category::LowCalories, $drafts[0]->category);
        self::assertSame('low_calories:2026-09-30', $drafts[0]->key);
        self::assertSame('Tu es à 1'.self::NBSP.'240 / 2'.self::NBSP.'350'.self::NBSP.'kcal'.self::NBSP.': il reste environ 1'.self::NBSP.'110'.self::NBSP.'kcal.', $drafts[0]->body);
        self::assertSame('/nutrition?ajouter=aliment', $drafts[0]->link);
    }

    /**
     * @return iterable<string, array{string, float, ?int, ?string}>
     */
    public static function noLowCalories(): iterable
    {
        yield 'before 20:00' => ['19:59', 1000.0, 2350, '12:30'];
        yield 'at 70 % of the target' => ['21:00', 1645.0, 2350, '12:30'];
        yield 'no target' => ['21:00', 1000.0, null, '12:30'];
        yield 'nothing logged today' => ['21:00', 0.0, 2350, null];
        yield 'a meal logged within the hour (spec §77)' => ['21:00', 1000.0, 2350, '20:15'];
    }

    #[DataProvider('noLowCalories')]
    public function testLowCaloriesIsNotToldWhenItWouldBeWrongOrRedundant(string $time, float $eaten, ?int $target, ?string $loggedAt): void
    {
        $logged = null === $loggedAt ? null : new \DateTimeImmutable('2026-09-30 '.$loggedAt, new \DateTimeZone('Europe/Paris'));

        self::assertSame([], new LowCaloriesRule()->evaluate(self::at($time, ['eatenKcal' => $eaten, 'targetKcal' => $target, 'lastLoggedAt' => $logged])));
    }

    public function testAMealLoggedExactlyAnHourAgoNoLongerHoldsItBack(): void
    {
        $at = new \DateTimeImmutable('2026-09-30 19:30', new \DateTimeZone('Europe/Paris'));

        self::assertCount(1, new LowCaloriesRule()->evaluate(self::at('20:30', ['eatenKcal' => 1000.0, 'targetKcal' => 2350, 'lastLoggedAt' => $at])));
        self::assertSame([], new LowCaloriesRule()->evaluate(self::at('20:29', ['eatenKcal' => 1000.0, 'targetKcal' => 2350, 'lastLoggedAt' => $at])));
    }

    public function testASessionTheWeekNeedsTodayIsTold(): void
    {
        // Wednesday: 5 days left, today included; 5 of 6 sessions still to do.
        $drafts = new WorkoutNotStartedRule()->evaluate(self::at('18:00', ['plannedPerWeek' => 6, 'sessionsThisWeek' => 1]));

        self::assertCount(1, $drafts);
        self::assertSame('workout_not_started:2026-09-30', $drafts[0]->key);
        self::assertSame('Il te reste 5 séances cette semaine'.self::NBSP.': c’est le moment de t’entraîner.', $drafts[0]->body);
        self::assertSame('/training', $drafts[0]->link);
    }

    /**
     * @return iterable<string, array{string, ?int, int, bool}>
     */
    public static function noSessionNeeded(): iterable
    {
        yield 'before 18:00' => ['17:59', 6, 1, false];
        yield 'time left in the week' => ['20:00', 3, 1, false];
        yield 'the week is done' => ['20:00', 3, 3, false];
        yield 'already started today' => ['20:00', 6, 1, true];
        yield 'no program' => ['20:00', null, 0, false];
    }

    #[DataProvider('noSessionNeeded')]
    public function testNoSessionIsAskedForWhenTheWeekDoesNotNeedOneToday(string $time, ?int $planned, int $done, bool $started): void
    {
        self::assertSame([], new WorkoutNotStartedRule()->evaluate(self::at($time, ['plannedPerWeek' => $planned, 'sessionsThisWeek' => $done, 'startedToday' => $started])));
    }

    public function testOnSundayTheLastSessionOfTheWeekIsNeeded(): void
    {
        $sunday = new \DateTimeImmutable('2026-10-04 18:30', new \DateTimeZone('Europe/Paris'));
        $drafts = new WorkoutNotStartedRule()->evaluate(self::at('18:30', ['now' => $sunday, 'plannedPerWeek' => 3, 'sessionsThisWeek' => 2]));

        self::assertSame('Il te reste 1 séance cette semaine'.self::NBSP.': c’est le moment de t’entraîner.', $drafts[0]->body);
    }

    public function testAMissingWeighInIsToldToSomeoneWhoUsuallyWeighs(): void
    {
        $drafts = new MissingWeighInRule()->evaluate(self::at('11:00', ['weighInsLastWeek' => 3]));

        self::assertSame('missing_weigh_in:2026-09-30', $drafts[0]->key);
        self::assertSame('/progression?ajouter=pesee', $drafts[0]->link);
        self::assertSame([], new MissingWeighInRule()->evaluate(self::at('10:59', ['weighInsLastWeek' => 7])));
        self::assertSame([], new MissingWeighInRule()->evaluate(self::at('12:00', ['weighInsLastWeek' => 2])), 'not a habit');
        self::assertSame([], new MissingWeighInRule()->evaluate(self::at('12:00', ['weighInsLastWeek' => 7, 'weighedToday' => true])));
    }

    public function testAStepJustReachedIsToldOldOnesAreNot(): void
    {
        $drafts = new GoalReachedRule()->evaluate(self::at('09:00', ['reachedSteps' => [
            new GoalStep('goal-1', 50.0, false, new \DateTimeImmutable('2026-09-12')),
            new GoalStep('goal-1', 55.0, false, new \DateTimeImmutable('2026-09-29')),
            new GoalStep('goal-1', 60.0, true, new \DateTimeImmutable('2026-09-30')),
        ]]));

        self::assertSame(['goal_reached:goal-1:55.0', 'goal_reached:goal-1:60.0'], array_map(static fn (Draft $draft): string => $draft->key, $drafts));
        self::assertSame('Jalon atteint'.self::NBSP.': 55,0'.self::NBSP.'kg', $drafts[0]->title);
        self::assertSame('Objectif atteint'.self::NBSP.': 60,0'.self::NBSP.'kg'.self::NBSP.'!', $drafts[1]->title);
        self::assertSame('Bravo'.self::NBSP.': tu peux fixer un nouvel objectif.', $drafts[1]->body);
        self::assertSame('/progression/objectif', $drafts[1]->link);
    }

    public function testAStepReachedTwoDaysAgoIsOldNews(): void
    {
        self::assertSame([], new GoalReachedRule()->evaluate(self::at('09:00', ['reachedSteps' => [new GoalStep('goal-1', 55.0, false, new \DateTimeImmutable('2026-09-28'))]])));
    }

    public function testEachWorkoutWithRecordsIsToldOnce(): void
    {
        $drafts = new RecordRule()->evaluate(self::at('19:00', ['recordSessions' => [new RecordSession('w-1', 1), new RecordSession('w-2', 3)]]));

        self::assertSame(['record:w-1', 'record:w-2'], array_map(static fn (Draft $draft): string => $draft->key, $drafts));
        self::assertSame(['Nouveau record', '3 nouveaux records'], array_map(static fn (Draft $draft): string => $draft->title, $drafts));
        self::assertSame('/training/seances/w-2', $drafts[1]->link);
    }

    public function testTheReportOfTheWeekJustEndedIsTold(): void
    {
        $drafts = new WeeklyReportRule()->evaluate(self::at('09:00', ['latestReportWeek' => new \DateTimeImmutable('2026-09-21')]));

        self::assertSame('weekly_report:2026-09-21', $drafts[0]->key);
        self::assertSame('/coach/rapports', $drafts[0]->link);
        self::assertSame([], new WeeklyReportRule()->evaluate(self::at('09:00', ['latestReportWeek' => new \DateTimeImmutable('2026-09-14')])), 'an older report is old news');
        self::assertSame([], new WeeklyReportRule()->evaluate(self::at('09:00')));
    }

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function quietHours(): iterable
    {
        yield 'across midnight, late' => ['23:00', '08:00', '23:30', true];
        yield 'across midnight, early' => ['23:00', '08:00', '07:59', true];
        yield 'across midnight, from the end' => ['23:00', '08:00', '08:00', false];
        yield 'across midnight, the day' => ['23:00', '08:00', '12:00', false];
        yield 'within a day, inside' => ['13:00', '14:00', '13:30', true];
        yield 'within a day, outside' => ['13:00', '14:00', '14:00', false];
    }

    #[DataProvider('quietHours')]
    public function testQuietHoursCoverTheirTimeAcrossMidnightToo(string $from, string $to, string $time, bool $quiet): void
    {
        self::assertSame($quiet, new QuietHours($from, $to)->covers(new \DateTimeImmutable('2026-09-30 '.$time)));
    }

    public function testQuietHoursAreTimes(): void
    {
        $this->expectExceptionMessage('quiet hours are HH:MM times');

        new QuietHours('24:00', '08:00');
    }

    public function testQuietHoursEndAtAnotherTimeNoneAtAllIsNoQuietHours(): void
    {
        $settings = NotificationSettings::defaults(new UserId('00000000-0000-7000-8000-00000000a1e7'), new \DateTimeImmutable('2026-09-30 10:00'));
        $settings->change(Category::cases(), null, new \DateTimeImmutable('2026-09-30 10:00'));
        self::assertNull($settings->quietHours());
        self::assertFalse($settings->isQuietAt(new \DateTimeImmutable('2026-09-30 03:00')));

        $this->expectExceptionMessage('quiet hours end at another time');
        new QuietHours('22:00', '22:00');
    }

    public function testTheQuietHoursFollowTheLocalClockOnTheNightTheClocksGoBack(): void
    {
        $quiet = new QuietHours('23:00', '08:00');
        $paris = new \DateTimeZone('Europe/Paris');
        // 2026-10-25: 03:00 (summer time) becomes 02:00 again; 07:30 UTC is 08:30 in Paris then.
        self::assertTrue($quiet->covers(new \DateTimeImmutable('2026-10-25 00:30 UTC')->setTimezone($paris)), '02:30 the first time');
        self::assertTrue($quiet->covers(new \DateTimeImmutable('2026-10-25 01:30 UTC')->setTimezone($paris)), '02:30 the second time');
        self::assertTrue($quiet->covers(new \DateTimeImmutable('2026-10-25 06:30 UTC')->setTimezone($paris)), '07:30 in winter time');
        self::assertFalse($quiet->covers(new \DateTimeImmutable('2026-10-25 07:00 UTC')->setTimezone($paris)), '08:00 in winter time: quiet no longer');
    }

    public function testNothingIsMadeDuringTheQuietHoursNorOfACategoryTurnedOff(): void
    {
        $settings = NotificationSettings::defaults(new UserId('00000000-0000-7000-8000-00000000a1e7'), new \DateTimeImmutable('2026-09-30'));
        $evening = ['eatenKcal' => 500.0, 'targetKcal' => 2350, 'weighInsLastWeek' => 5, 'recordSessions' => [new RecordSession('w-1', 1)]];

        self::assertSame(
            [Category::LowCalories, Category::MissingWeighIn, Category::Record],
            array_map(static fn (Draft $draft): Category => $draft->category, Checks::drafts(self::at('22:59', $evening), $settings)),
        );
        self::assertSame([], Checks::drafts(self::at('23:00', $evening), $settings), 'quiet from 23:00 by default');

        $settings->change([Category::Record], null, new \DateTimeImmutable('2026-09-30'));
        self::assertSame(
            [Category::Record],
            array_map(static fn (Draft $draft): Category => $draft->category, Checks::drafts(self::at('23:30', $evening), $settings)),
        );
    }

    public function testSettingsKeepTheCategoriesOffAndANewCategoryIsOn(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 10:00');
        $settings = NotificationSettings::defaults(new UserId('00000000-0000-7000-8000-00000000a1e7'), $now);
        foreach (Category::cases() as $category) {
            self::assertTrue($settings->isEnabled($category));
        }
        self::assertSame(['23:00', '08:00'], [$settings->quietHours()?->from, $settings->quietHours()?->to]);

        $settings->change([Category::Record, Category::GoalReached], new QuietHours('22:00', '07:00'), $now);
        self::assertTrue($settings->isEnabled(Category::Record));
        self::assertFalse($settings->isEnabled(Category::LowCalories));
        self::assertSame('22:00', $settings->quietHours()?->from);
        self::assertFalse($settings->allOff());
        $settings->change([], null, $now);
        self::assertTrue($settings->allOff());
    }
}
