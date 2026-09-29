<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * A signed-in user's progression on a fixed Wednesday (weeks and "today" never move with the
 * real date); `$today` is that day, as a device in the server's time zone sends it.
 */
abstract class ProgressionApiTestCase extends IdentityApiTestCase
{
    use ClockSensitiveTrait;
    use JsonDocuments;

    protected const string TODAY = '2026-09-30';

    protected \DateTimeImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(self::TODAY.' 10:00:00');
        $this->client->disableReboot();
        $this->today = new \DateTimeImmutable(self::TODAY);
    }

    protected function daysAgo(int $days): string
    {
        return $this->today->modify(\sprintf('-%d days', $days))->format('Y-m-d');
    }

    protected function weigh(string $day, float $kg): void
    {
        $this->call('PUT', '/api/progression/weigh-ins/'.$day, ['weightKg' => $kg]);
        self::assertResponseStatusCodeSame(200);
    }
}
