<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use App\Dev\Demo\DemoSeeder;
use App\Tests\Support\Database\QueryCounter;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Signed in as a demo account with a couple of months of history (ADR-0032): the data the
 * performance checks run on.
 */
abstract class DemoAccountTestCase extends ApiTestCase
{
    protected const int DAYS = 60;
    /** Accounts seeded besides the one signed in (the query plans' tests want several). */
    protected const int OTHER_ACCOUNTS = 0;

    protected static ?bool $alwaysBootKernel = true;

    protected Client $client;
    protected string $today;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->today = $now->format('Y-m-d');
        $seeder = self::getContainer()->get(DemoSeeder::class);
        // The profiler keeps every statement of a request; seeding is thousands of them.
        $debug = self::getContainer()->get('doctrine.debug_data_holder');
        $forget = static fn () => $debug->reset();
        // Other accounts first: their rows share every table, so a plan has to find the user's own.
        for ($other = 1; $other <= static::OTHER_ACCOUNTS; ++$other) {
            $seeder->seed(\sprintf('other-%s@bulkpilot.local', bin2hex(random_bytes(4))), static::DAYS, $now, $forget, $other);
        }
        // An address of its own each run: sign-ins are rate limited by address, and the limiter outlives a test.
        $email = \sprintf('demo-%s@bulkpilot.local', bin2hex(random_bytes(4)));
        $seeder->seed($email, static::DAYS, $now, $forget);
        $this->call('POST', '/api/auth/sign-in', ['email' => $email, 'password' => DemoSeeder::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    /**
     * The reads of the app's main screens, with the statements each one is allowed (the
     * measured count — one of them reads the session's user — plus 2).
     *
     * @return array<string, array{string, int}>
     */
    protected function readings(): array
    {
        $yesterday = new \DateTimeImmutable($this->today)->modify('-1 day')->format('Y-m-d');
        $history = $this->call('GET', '/api/workouts?limit=1')->toArray();
        $workout = \is_array($history[0] ?? null) && \is_string($history[0]['id'] ?? null) ? $history[0]['id'] : '';
        $programs = $this->call('GET', '/api/programs')->toArray();
        $program = \is_array($programs[0] ?? null) && \is_string($programs[0]['id'] ?? null) ? $programs[0]['id'] : '';

        return [
            'dashboard layout' => ['/api/me/dashboard', 4],
            'account' => ['/api/me', 5],
            'nutrition day' => ['/api/nutrition/days/'.$yesterday, 6],
            'nutrition averages' => ['/api/nutrition/averages?days=30&today='.$this->today, 5],
            'nutrition history' => ['/api/nutrition/history?period=3m&today='.$this->today, 5],
            'weight summary' => ['/api/progression/weight?period=1y&today='.$this->today, 6],
            'weigh-ins' => ['/api/progression/weigh-ins?limit=50', 4],
            'weight goal' => ['/api/progression/weight-goal?today='.$this->today, 5],
            'measurements' => ['/api/progression/measurements?period=3m&today='.$this->today, 5],
            'next workout' => ['/api/training/next-workout', 7],
            'training analytics' => ['/api/training/analytics?period=3m&today='.$this->today, 4],
            'training week' => ['/api/training/week?today='.$this->today, 5],
            'workouts history' => ['/api/workouts?limit=20', 4],
            'workout' => ['/api/workouts/'.$workout, 6],
            'programs' => ['/api/programs', 6],
            'workout preview' => ['/api/workouts/preview?programId='.$program.'&day=0', 8],
            'exercises' => ['/api/exercises', 5],
            'food search' => ['/api/foods?q=riz', 5],
            'recent foods' => ['/api/foods/recent', 5],
            'recipes' => ['/api/recipes', 5],
            'favorite meals' => ['/api/nutrition/favorite-meals', 5],
            'coach' => ['/api/coach?today='.$this->today, 6],
            'coach history' => ['/api/coach/recommendations?limit=20', 4],
            'coach reports' => ['/api/coach/reports', 4],
            'notifications' => ['/api/notifications?limit=20', 4],
            'unread notifications' => ['/api/notifications/unread', 4],
            'exports' => ['/api/exports', 4],
            'activity' => ['/api/me/activity?limit=20', 4],
        ];
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return list<array{sql: string, params: array<int|string, mixed>}>
     */
    protected function measure(string $method, string $uri, ?array $json = null): array
    {
        $counter = self::getContainer()->get(QueryCounter::class);
        $counter->reset();
        $response = $this->call($method, $uri, $json);
        self::assertLessThan(300, $response->getStatusCode(), $uri.': '.$response->getContent(false));

        return $counter->executed();
    }

    /**
     * @param array<string, mixed>|null $json
     */
    protected function call(string $method, string $uri, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => ['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0']];
        if (null !== $json) {
            $options['json'] = $json;
        }

        return $this->client->request($method, $uri, $options);
    }
}
