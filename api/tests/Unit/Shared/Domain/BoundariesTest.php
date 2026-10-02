<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Calendar\Days;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Text\French;
use PHPUnit\Framework\TestCase;

/**
 * Calendar periods and French figures at their edges.
 */
final class BoundariesTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    public function testPeriodsStartAtMidnightTheRightNumberOfDaysBack(): void
    {
        $today = new \DateTimeImmutable('2026-09-30 15:30');

        self::assertSame('2026-07-02 00:00', Period::Quarter->firstDay($today)?->format('Y-m-d H:i'), '91 days, today included');
        self::assertSame('2026-04-02 00:00', Period::HalfYear->firstDay($today)?->format('Y-m-d H:i'), '182 days');
        self::assertSame('2026-09-28 00:00', Days::weekOf($today)->format('Y-m-d H:i'), 'the Monday, at midnight');
    }

    public function testSignsAreForWhatRoundsAwayFromZero(): void
    {
        self::assertSame('0,0'.self::NBSP.'kg', French::pace(0.004), 'rounds to nothing: no sign');
        self::assertSame('0,0'.self::NBSP.'kg', French::pace(-0.004));
        self::assertSame('+0,1'.self::NBSP.'kg', French::pace(0.104), 'one decimal when the hundredth is 0');
        self::assertSame('+0,25'.self::NBSP.'kg', French::pace(0.25));
        self::assertSame('0'.self::NBSP.'kcal', French::signedKcal(0));
        self::assertSame('+150'.self::NBSP.'kcal', French::signedKcal(150));
    }
}
