<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use App\Tests\Support\Api\IdentityApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Every per-account write limiter (spec §101) answers 429 with Retry-After once its budget is
 * spent. The budgets are spent through the limiter itself: hundreds of real writes would only
 * slow the suite down. Sign-in, registration, second factor, barcode and export limits are
 * exercised by real requests in their own tests.
 */
final class RateLimitsTest extends IdentityApiTestCase
{
    /**
     * @return iterable<string, array{string, string, string, array<string, mixed>|null}>
     */
    public static function limitedWrites(): iterable
    {
        yield 'training' => ['training_writes', 'PUT', '/api/workouts/0199a0b1-0000-7000-8000-0000000b0001', ['startedAt' => new \DateTimeImmutable('-1 hour')->format(\DATE_ATOM)]];
        yield 'progression' => ['progression_writes', 'PUT', '/api/progression/weigh-ins/'.new \DateTimeImmutable()->format('Y-m-d'), ['weightKg' => 80]];
        yield 'preferences' => ['preference_writes', 'PUT', '/api/me/time-zone', ['timeZone' => 'Europe/Paris']];
        yield 'coach' => ['coach_writes', 'PUT', '/api/coach/settings', ['personality' => 'direct', 'paceKgPerWeek' => null]];
        yield 'notifications' => ['notification_writes', 'POST', '/api/notifications/check', []];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('limitedWrites')]
    public function testASpentBudgetAnswers429WithWhenToRetry(string $limiter, string $method, string $uri, ?array $body): void
    {
        $this->client->disableReboot();
        $userId = $this->registerVerified();
        $factory = self::getContainer()->get('limiter.'.$limiter);
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $factory);
        $bucket = $factory->create(hash('sha256', $limiter.'|'.$userId));
        for ($spent = 0; $bucket->consume()->isAccepted(); ++$spent) {
            self::assertLessThan(10_000, $spent, 'the budget is finite');
        }

        $response = $this->call($method, $uri, $body);

        self::assertSame(429, $response->getStatusCode(), $method.' '.$uri);
        $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
        self::assertIsString($retryAfter);
        self::assertGreaterThanOrEqual(1, (int) $retryAfter, 'Retry-After says when to send it again');
    }
}
