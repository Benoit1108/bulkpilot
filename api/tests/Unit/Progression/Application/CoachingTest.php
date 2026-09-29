<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application;

use App\Progression\Application\Coaching\WeighInReadings;
use App\Tests\Support\Progression\ProgressionTestCase;

final class CoachingTest extends ProgressionTestCase
{
    public function testTheReadingIsTheWeekAverageTheSpeedAndHowOftenOneWeighed(): void
    {
        $this->weigh(['2026-08-20' => 50.0, '2026-09-08' => 60.0, '2026-09-15' => 60.5, '2026-09-22' => 61.0, '2026-09-24' => 61.4, '2026-09-26' => 61.6, '2026-09-30' => 70.0]);
        $this->weigh(['2026-09-26' => 90.0], $this->sam);

        $reading = new WeighInReadings($this->weighIns)->reading($this->alex, self::day('2026-09-29'));

        self::assertSame(61.5, $reading->average7Kg, 'the 24th and the 26th; the 30th is after the day');
        self::assertSame(0.61, $reading->kgPerWeek, 'from the 8th: the 20th of August is more than 4 weeks back');
        self::assertSame([2, 5], [$reading->weighIns7, $reading->weighIns28]);
    }

    public function testTooFewWeighInsGiveNoSpeed(): void
    {
        $this->weigh(['2026-09-20' => 60.0, '2026-09-25' => 60.4, '2026-09-29' => 60.6]);

        $reading = new WeighInReadings($this->weighIns)->reading($this->alex, self::day('2026-09-29'));

        self::assertNull($reading->kgPerWeek);
        self::assertSame([60.5, 2, 3], [$reading->average7Kg, $reading->weighIns7, $reading->weighIns28]);
        self::assertSame([null, null, 0, 0], (static fn ($empty): array => [$empty->average7Kg, $empty->kgPerWeek, $empty->weighIns7, $empty->weighIns28])(new WeighInReadings($this->weighIns)->reading($this->sam, self::day('2026-09-29'))));
    }
}
