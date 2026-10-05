<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Infrastructure\Monitoring;

use App\System\Domain\Monitoring\Beat;
use App\System\Infrastructure\Monitoring\HttpPings;
use App\Tests\Support\System\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpPingsTest extends TestCase
{
    private const string CHECK = 'https://hc-ping.com/0199a0b1-0000-7000-8000-0000000000c1';

    public function testABeatIsAGetAndAFailureAPostToFailWithTheReason(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'timeout' => $options['timeout'], 'max_duration' => $options['max_duration'], 'body' => $options['body'] ?? ''];

            return new MockResponse('OK');
        });
        $pings = new HttpPings($client, new RecordingLogger(), self::CHECK.'/', '', self::CHECK.'2');

        $pings->up(Beat::Scheduler);
        $pings->down(Beat::BackupVerify, str_repeat('é', 300));
        $pings->up(Beat::Backup);

        self::assertCount(2, $requests, 'a beat without URL is not watched from outside');
        self::assertSame(['method' => 'GET', 'url' => self::CHECK, 'timeout' => 3.0, 'max_duration' => 3.0, 'body' => ''], $requests[0]);
        self::assertSame(['POST', self::CHECK.'2/fail'], [$requests[1]['method'], $requests[1]['url']]);
        self::assertSame(str_repeat('é', 200), $requests[1]['body'], 'cut by characters, not bytes');
    }

    public function testACheckThatCannotBeReachedIsLoggedNotThrown(): void
    {
        $logger = new RecordingLogger();
        $pings = new HttpPings(new MockHttpClient(static fn (): never => throw new TransportException('timeout')), $logger, self::CHECK, '', '');

        $pings->up(Beat::Scheduler);

        self::assertSame(['warning system.heartbeat_not_sent'], $logger->lines());
    }

    public function testAWrongUrlAnsweringAnErrorIsLoggedToo(): void
    {
        $logger = new RecordingLogger();
        $pings = new HttpPings(new MockHttpClient(new MockResponse('not found', ['http_code' => 404])), $logger, self::CHECK, '', '');

        $pings->up(Beat::Scheduler);

        self::assertSame(['warning system.heartbeat_not_sent'], $logger->lines());
    }
}
