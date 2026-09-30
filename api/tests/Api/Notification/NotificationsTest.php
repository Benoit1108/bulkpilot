<?php

declare(strict_types=1);

namespace App\Tests\Api\Notification;

use App\Tests\Support\Api\TrainingApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The notifications as the app opens (ADR-0029), on Wednesday 2026-09-30. The server's clock is
 * UTC and a new account follows Paris: 18:30 UTC is 20:30 there.
 */
final class NotificationsTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;

    private const string EVENING = '2026-09-30 18:30:00';
    private const string NBSP = "\u{00A0}";
    private const array ALL = ['low_calories' => true, 'workout_not_started' => true, 'missing_weigh_in' => true, 'goal_reached' => true, 'record' => true, 'weekly_report' => true];

    private int $sets = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-09-30 08:00:00');
        $this->client->disableReboot();
    }

    /**
     * A day of little eating: a 2 500 kcal target, 1 000 kcal logged at lunch.
     */
    private function littleEaten(): void
    {
        $this->call('POST', '/api/nutrition/targets', ['effectiveFrom' => '2026-09-30', 'energyKcal' => 2500, 'proteinGrams' => 150, 'carbohydrateGrams' => 300, 'fatGrams' => 75]);
        self::assertResponseStatusCodeSame(201);
        $food = self::string($this->call('POST', '/api/foods', ['name' => 'Repas type', 'energyKcal' => 250, 'protein' => 15, 'carbohydrate' => 25, 'fat' => 10])->toArray(), 'id');
        $this->call('POST', '/api/nutrition/entries', ['foodId' => $food, 'day' => '2026-09-30', 'slot' => 'lunch', 'grams' => 400]);
        self::assertResponseStatusCodeSame(201);
    }

    private function workout(string $id, string $startedAt, float $load): void
    {
        $at = static fn (string $when): string => new \DateTimeImmutable($startedAt.' UTC')->modify($when)->format(\DATE_ATOM);
        $this->call('PUT', '/api/workouts/'.$id, ['startedAt' => $at('+0 minutes')]);
        $this->call('PUT', '/api/workouts/'.$id.'/exercises/0', ['exerciseId' => $this->exerciseId('developpe couche barre')]);
        $this->call('PUT', \sprintf('/api/workouts/%s/sets/0199a0b1-0000-7000-8000-%012d', $id, ++$this->sets), ['exerciseIndex' => 0, 'kind' => 'normal', 'loadKg' => $load, 'reps' => 8, 'doneAt' => $at('+10 minutes')]);
        self::assertResponseStatusCodeSame(204);
        $this->call('POST', '/api/workouts/'.$id.'/finish', ['finishedAt' => $at('+45 minutes')]);
        self::assertResponseStatusCodeSame(200);
    }

    private function check(): void
    {
        $this->call('POST', '/api/notifications/check');
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inbox(string $query = ''): array
    {
        /** @var list<array<string, mixed>> */
        return $this->getJson('/api/notifications'.$query);
    }

    /**
     * @param list<array<string, mixed>> $notifications
     *
     * @return list<string>
     */
    private static function categories(array $notifications): array
    {
        return array_map(static fn (array $notification): string => self::string($notification, 'category'), $notifications);
    }

    private function unread(): int
    {
        return self::int($this->getJson('/api/notifications/unread'), 'count');
    }

    public function testTheNotificationsNeedASignedInUser(): void
    {
        $this->call('POST', '/api/notifications/check');
        self::assertResponseStatusCodeSame(401);
        $this->call('GET', '/api/notifications');
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheEveningCheckTellsTheLowCaloriesAndTheRecordOfTodaysWorkout(): void
    {
        $this->register();
        $this->littleEaten();
        self::mockTime('2026-09-28 17:00:00');
        $this->workout('0199a0b1-0000-7000-8000-00000000f001', '2026-09-28 16:00:00', 60.0);
        self::mockTime(self::EVENING);
        $this->workout('0199a0b1-0000-7000-8000-00000000f002', '2026-09-30 16:00:00', 70.0);

        $this->check();

        $inbox = $this->inbox();
        self::assertEqualsCanonicalizing(['low_calories', 'record'], self::categories($inbox));
        $calories = array_values(array_filter($inbox, static fn (array $notification): bool => 'low_calories' === $notification['category']))[0];
        self::assertSame('Tu es à 1'.self::NBSP.'000 / 2'.self::NBSP.'500'.self::NBSP.'kcal'.self::NBSP.': il reste environ 1'.self::NBSP.'500'.self::NBSP.'kcal.', $calories['body']);
        self::assertSame(['/nutrition?ajouter=aliment', null], [$calories['link'], $calories['readAt']]);
        $record = array_values(array_filter($inbox, static fn (array $notification): bool => 'record' === $notification['category']))[0];
        self::assertSame('/training/seances/0199a0b1-0000-7000-8000-00000000f002', $record['link']);
        self::assertSame(2, $this->unread());

        // Opening the app again a minute later, or later that evening: nothing is told twice.
        self::mockTime('2026-09-30 18:31:00');
        $this->check();
        self::mockTime('2026-09-30 19:30:00');
        $this->check();
        self::assertCount(2, $this->inbox());
    }

    public function testReadingOneThenAllAndTheCursor(): void
    {
        $this->register();
        $this->littleEaten();
        self::mockTime('2026-09-28 17:00:00');
        $this->workout('0199a0b1-0000-7000-8000-00000000f001', '2026-09-28 16:00:00', 60.0);
        self::mockTime(self::EVENING);
        $this->workout('0199a0b1-0000-7000-8000-00000000f002', '2026-09-30 16:00:00', 70.0);
        $this->check();
        [$first, $second] = $this->inbox();

        self::assertSame([$first], $this->inbox('?limit=1'));
        self::assertSame([$second], $this->inbox('?limit=1&before='.rawurlencode(self::string($first, 'createdAt')).'&beforeId='.self::string($first, 'id')), 'made at the same time: the id breaks the tie');
        $this->call('GET', '/api/notifications?before=yesterday');
        self::assertResponseStatusCodeSame(400);

        $read = $this->call('POST', '/api/notifications/'.self::string($second, 'id').'/read')->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('2026-09-30T18:30:00+00:00', $read['readAt']);
        self::assertSame(1, $this->unread());

        $this->call('POST', '/api/notifications/read-all');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->unread());
        self::assertNotNull($this->getJson('/api/notifications/'.self::string($first, 'id'))['readAt']);

        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->inbox(), 'Sam sees only Sam\'s');
        $this->call('POST', '/api/notifications/'.self::string($first, 'id').'/read');
        self::assertResponseStatusCodeSame(404);
        $this->call('GET', '/api/notifications/not-an-id');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheChecksFollowTheUsersTimeZoneAndQuietHours(): void
    {
        $this->register();
        $this->littleEaten();
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Asia/Tokyo']);
        self::assertResponseStatusCodeSame(204);
        self::mockTime(self::EVENING);

        $this->check();

        self::assertSame([], $this->inbox(), '03:30 on the 1st in Tokyo: the quiet hours');
    }

    public function testTheEveningIsTheUsersOwn(): void
    {
        $this->register();
        $this->littleEaten();
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Asia/Tokyo']);
        self::mockTime('2026-09-30 11:30:00');

        $this->check();

        self::assertSame(['low_calories'], self::categories($this->inbox()), '20:30 in Tokyo, 13:30 in Paris');
    }

    public function testWithoutQuietHoursTheNightIsNotSilent(): void
    {
        $this->register();
        $this->littleEaten();
        $this->call('PUT', '/api/notifications/settings', ['categories' => self::ALL, 'quietFrom' => null, 'quietTo' => null]);
        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->getJson('/api/notifications/settings')['quietFrom']);
        self::mockTime('2026-09-30 21:30:00');

        $this->check();

        self::assertSame(['low_calories'], self::categories($this->inbox()), '23:30 in Paris');
    }

    public function testTheChecksAreLimitedButNeverRefusedForBeingConcurrent(): void
    {
        $this->register();
        for ($i = 0; $i < 60; ++$i) {
            $this->check();
        }
        $this->call('POST', '/api/notifications/check');
        self::assertResponseStatusCodeSame(429);
    }

    public function testCategoriesTurnedOffAreNotTold(): void
    {
        $this->register();
        $this->littleEaten();
        self::mockTime(self::EVENING);
        $settings = $this->getJson('/api/notifications/settings');
        self::assertSame(['low_calories' => true, 'workout_not_started' => true, 'missing_weigh_in' => true, 'goal_reached' => true, 'record' => true, 'weekly_report' => true], $settings['categories']);
        self::assertSame(['23:00', '08:00'], [$settings['quietFrom'], $settings['quietTo']]);

        $saved = $this->call('PUT', '/api/notifications/settings', ['categories' => ['low_calories' => false] + self::array($settings, 'categories'), 'quietFrom' => '22:00', 'quietTo' => '07:30'])->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame([false, '22:00', '07:30'], [self::at($saved, 'categories.low_calories'), $saved['quietFrom'], $saved['quietTo']]);

        $this->check();
        self::assertSame([], $this->inbox());
    }

    public function testSettingsAreEveryCategoryAndTimes(): void
    {
        $this->register();
        $all = self::ALL;

        foreach ([
            ['categories' => ['record' => true], 'quietFrom' => '23:00', 'quietTo' => '08:00'],
            ['categories' => ['unknown' => true] + $all, 'quietFrom' => '23:00', 'quietTo' => '08:00'],
            ['categories' => ['record' => 'yes'] + $all, 'quietFrom' => '23:00', 'quietTo' => '08:00'],
            ['categories' => $all, 'quietFrom' => '25:00', 'quietTo' => '08:00'],
            ['categories' => $all, 'quietFrom' => '22:00', 'quietTo' => null],
            ['categories' => $all, 'quietFrom' => '22:00', 'quietTo' => '22:00'],
        ] as $body) {
            $this->call('PUT', '/api/notifications/settings', $body);
            self::assertResponseStatusCodeSame(422);
            self::assertProblemCode('notification.invalid_settings');
        }
    }
}
