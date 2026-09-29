<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use App\Shared\Domain\Calendar\Period;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Api\PeriodQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CalendarDaysTest extends TestCase
{
    public function testADayIsReadStrictly(): void
    {
        self::assertSame('2026-02-28', CalendarDays::parse('2026-02-28')?->format('Y-m-d'));
        foreach (['2026-02-30', '2026-2-28', 20260228, null, '28/02/2026'] as $value) {
            self::assertNull(CalendarDays::parse($value));
        }
    }

    #[DataProvider('todays')]
    public function testTheDevicesTodayIsWithinADayOfTheServers(?string $sent, ?string $today): void
    {
        $clock = new MockClock('2026-09-30 23:30:00');
        if (null === $today) {
            $this->expectException(BadRequestHttpException::class);
        }

        self::assertSame($today, CalendarDays::today($sent, $clock)->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function todays(): iterable
    {
        yield 'none: the server\'s' => [null, '2026-09-30'];
        yield 'a day ahead' => ['2026-10-01', '2026-10-01'];
        yield 'a day behind' => ['2026-09-29', '2026-09-29'];
        yield 'two days ahead' => ['2026-10-02', null];
        yield 'not a day' => ['tomorrow', null];
    }

    public function testAChartShowsThreeMonthsUntilToldOtherwise(): void
    {
        $clock = new MockClock('2026-09-30 10:00:00');

        self::assertSame([Period::Quarter, '2026-09-30'], array_map(static fn ($value) => $value instanceof \DateTimeImmutable ? $value->format('Y-m-d') : $value, PeriodQuery::read(null, $clock)));
        self::assertSame(Period::Year, PeriodQuery::read(new Request(['period' => '1y']), $clock)[0]);
        $this->expectException(BadRequestHttpException::class);
        PeriodQuery::read(new Request(['period' => '2w']), $clock);
    }

    public function testAPeriodStartsSoManyDaysBeforeTodayIncluded(): void
    {
        $today = new \DateTimeImmutable('2026-09-30');

        self::assertSame(['2026-09-24', '2026-09-01', '2025-10-01'], [Period::Week->firstDay($today)?->format('Y-m-d'), Period::Month->firstDay($today)?->format('Y-m-d'), Period::Year->firstDay($today)?->format('Y-m-d')]);
        self::assertNull(Period::All->firstDay($today));
    }
}
