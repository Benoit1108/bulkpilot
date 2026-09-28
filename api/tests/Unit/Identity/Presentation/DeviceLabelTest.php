<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Presentation;

use App\Identity\Presentation\Http\DeviceLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceLabelTest extends TestCase
{
    #[DataProvider('userAgents')]
    public function testUserAgentsAreSummarised(?string $userAgent, string $expected): void
    {
        self::assertSame($expected, DeviceLabel::fromUserAgent($userAgent));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function userAgents(): iterable
    {
        yield 'android chrome' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36', 'Chrome · Android'];
        yield 'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 19_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/19.0 Mobile/15E148 Safari/604.1', 'Safari · iOS'];
        yield 'windows edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36 Edg/140.0', 'Edge · Windows'];
        yield 'mac firefox' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 15.0; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox · macOS'];
        yield 'unknown client' => ['curl/8.10', 'Appareil inconnu'];
        yield 'no header' => [null, 'Appareil inconnu'];
    }
}
