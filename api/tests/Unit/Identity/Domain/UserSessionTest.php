<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

final class UserSessionTest extends TestCase
{
    private const string OWNER = '0199a0b1-7c3e-7d4a-9b2f-000000000001';

    public function testANewSessionIsActiveAndBelongsToItsUser(): void
    {
        $session = $this->open(' Firefox · Linux ');

        self::assertTrue($session->isActive(new \DateTimeImmutable('2026-09-28 10:00:00')));
        self::assertTrue($session->belongsTo(new UserId(self::OWNER)));
        self::assertFalse($session->belongsTo(new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000002')));
        self::assertSame('Firefox · Linux', $session->deviceLabel());
    }

    public function testAnEmptyDeviceLabelFallsBackAndLongOnesAreTruncated(): void
    {
        self::assertSame('Appareil inconnu', $this->open('   ')->deviceLabel());
        self::assertSame(100, mb_strlen($this->open(str_repeat('é', 150))->deviceLabel()));
    }

    public function testActivityIsRecordedAtMostEveryFiveMinutes(): void
    {
        $session = $this->open();
        $opened = $session->openedAt();

        self::assertFalse($session->recordActivity($opened->modify('+299 seconds')));
        self::assertSame($opened->format(\DATE_ATOM), $session->lastSeenAt()->format(\DATE_ATOM));
        self::assertTrue($session->recordActivity($opened->modify('+300 seconds')));
        self::assertSame($opened->modify('+300 seconds')->format(\DATE_ATOM), $session->lastSeenAt()->format(\DATE_ATOM));
    }

    public function testRevocationIsFinalAndKeepsTheFirstDate(): void
    {
        $session = $this->open();

        $session->revoke(new \DateTimeImmutable('2026-09-28 11:00:00'));
        $session->revoke(new \DateTimeImmutable('2026-09-28 12:00:00'));

        self::assertFalse($session->isActive(new \DateTimeImmutable('2026-09-28 12:00:00')));
    }

    public function testADeviceIdleForThirtyDaysIsNoLongerActive(): void
    {
        $session = $this->open();
        $opened = $session->openedAt();

        self::assertTrue($session->isActive($opened->modify('+30 days -1 second')));
        self::assertFalse($session->isActive($opened->modify('+30 days')));

        $session->recordActivity($opened->modify('+29 days'));
        self::assertTrue($session->isActive($opened->modify('+30 days')), 'activity pushes the expiry');
    }

    private function open(string $device = 'Chrome · Android'): UserSession
    {
        return UserSession::open(
            new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000a'),
            new UserId(self::OWNER),
            $device,
            new \DateTimeImmutable('2026-09-28 10:00:00'),
        );
    }
}
