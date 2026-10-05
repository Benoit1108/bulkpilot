<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Infrastructure\Monitoring;

use App\System\Domain\Monitoring\Beat;
use App\System\Infrastructure\Monitoring\DoctrineBeats;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineBeatsTest extends KernelTestCase
{
    public function testTheLastBeatOfEachKindIsKept(): void
    {
        $beats = self::getContainer()->get(DoctrineBeats::class);
        self::assertNull($beats->lastOf(Beat::Backup));

        $beats->record(Beat::Backup, new \DateTimeImmutable('2026-10-01 02:30:05+00:00'));
        $beats->record(Beat::Backup, new \DateTimeImmutable('2026-10-02 02:30:07+00:00'));
        $beats->record(Beat::Scheduler, new \DateTimeImmutable('2026-10-02 12:15:00+02:00'));

        self::assertSame('2026-10-02T02:30:07+00:00', $beats->lastOf(Beat::Backup)?->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));
        self::assertSame('2026-10-02T10:15:00+00:00', $beats->lastOf(Beat::Scheduler)?->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));
        self::assertNull($beats->lastOf(Beat::BackupVerify));
        self::assertSame('2026-10-01T02:30:05+00:00', $beats->firstOf(Beat::Backup)?->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), 'the first beat is kept');
    }
}
