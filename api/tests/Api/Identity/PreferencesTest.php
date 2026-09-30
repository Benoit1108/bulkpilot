<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;
use Doctrine\DBAL\Connection;

final class PreferencesTest extends IdentityApiTestCase
{
    private const array DEFAULT_ORDER = ['weight', 'nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report'];

    public function testTheTimeZoneIsTheDevicesOnceSent(): void
    {
        $this->register();
        self::assertSame('Europe/Paris', $this->call('GET', '/api/me')->toArray()['timeZone'], 'until a device says otherwise');

        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'America/Montreal']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('America/Montreal', $this->call('GET', '/api/me')->toArray()['timeZone']);
    }

    /**
     * The devices send their zone silently, often while the user confirms their email or changes a
     * password: preferences never touch the account row, so they never make those conflict.
     */
    public function testPreferencesNeverChangeTheAccount(): void
    {
        $this->register();
        $version = $this->accountVersion();

        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Asia/Tokyo']);
        self::assertResponseStatusCodeSame(204);
        $this->call('PUT', '/api/me/dashboard', ['widgets' => array_map(static fn (string $key): array => ['key' => $key, 'visible' => true], array_reverse(self::DEFAULT_ORDER))]);
        self::assertResponseIsSuccessful();

        self::assertSame($version, $this->accountVersion());
        self::assertSame('Asia/Tokyo', $this->call('GET', '/api/me')->toArray()['timeZone'], 'arranging the dashboard kept the zone');
    }

    public function testAnUnknownTimeZoneIsRefused(): void
    {
        $this->register();

        foreach (['Mars/Olympus', 'europe/paris', '+02:00'] as $zone) {
            $this->call('PUT', '/api/me/time-zone', ['timeZone' => $zone]);
            self::assertResponseStatusCodeSame(422, $zone);
            self::assertProblemCode('identity.invalid_time_zone');
        }
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Europe/Paris', $this->call('GET', '/api/me')->toArray()['timeZone']);
    }

    public function testTheDashboardStartsInTheDefaultOrderThenKeepsTheUsers(): void
    {
        $this->register();
        $widgets = $this->widgets();
        self::assertSame(self::DEFAULT_ORDER, array_column($widgets, 'key'));
        self::assertSame(array_fill(0, 8, true), array_column($widgets, 'visible'));

        $arranged = [['key' => 'coach', 'visible' => true], ['key' => 'weight', 'visible' => true], ['key' => 'meals', 'visible' => false]];
        foreach (self::DEFAULT_ORDER as $key) {
            if (!\in_array($key, ['coach', 'weight', 'meals'], true)) {
                $arranged[] = ['key' => $key, 'visible' => 'report' !== $key];
            }
        }
        $saved = $this->call('PUT', '/api/me/dashboard', ['widgets' => $arranged])->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame($arranged, $saved['widgets']);

        $this->useDevice('phone');
        $this->signIn();
        self::assertSame($arranged, $this->widgets(), 'the same dashboard on every device');
    }

    public function testEveryWidgetIsPlacedExactlyOnce(): void
    {
        $this->register();
        $all = array_map(static fn (string $key): array => ['key' => $key, 'visible' => true], self::DEFAULT_ORDER);

        foreach ([
            'one missing' => \array_slice($all, 1),
            'one twice' => [...$all, $all[0]],
            'an unknown one' => [...\array_slice($all, 1), ['key' => 'horoscope', 'visible' => true]],
        ] as $case => $widgets) {
            $this->call('PUT', '/api/me/dashboard', ['widgets' => $widgets]);
            self::assertResponseStatusCodeSame(422, $case);
            self::assertProblemCode('identity.invalid_dashboard_layout');
        }
        $this->call('PUT', '/api/me/dashboard', ['widgets' => [['key' => 'weight']]]);
        self::assertResponseStatusCodeSame(422, 'visible is required');
        self::assertSame(self::DEFAULT_ORDER, array_column($this->widgets(), 'key'));
    }

    public function testPreferencesNeedAnAccount(): void
    {
        $this->call('GET', '/api/me/dashboard');
        self::assertResponseStatusCodeSame(401);
        $this->call('PUT', '/api/me/time-zone', ['timeZone' => 'Europe/Paris']);
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return list<array{key: string, visible: bool}>
     */
    private function widgets(): array
    {
        /** @var list<array{key: string, visible: bool}> */
        return $this->call('GET', '/api/me/dashboard')->toArray()['widgets'];
    }

    private function accountVersion(): int
    {
        $version = self::getContainer()->get(Connection::class)->fetchOne("SELECT version FROM users WHERE email = 'alex@example.com'");
        self::assertIsInt($version);

        return $version;
    }
}
