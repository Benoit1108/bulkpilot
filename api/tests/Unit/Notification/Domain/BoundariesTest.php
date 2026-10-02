<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\Check\LowCaloriesRule;
use App\Notification\Domain\Check\RecordRule;
use App\Notification\Domain\Check\RecordSession;
use App\Notification\Domain\Check\Situation;
use App\Notification\Domain\Check\WorkoutNotStartedRule;
use App\Notification\Domain\Settings\QuietHours;
use PHPUnit\Framework\TestCase;

/**
 * The edges of the notification rules.
 */
final class BoundariesTest extends TestCase
{
    private const string NBSP = "\u{00A0}";

    public function testWhatIsLeftIsRoundedToTenKcal(): void
    {
        $drafts = new LowCaloriesRule()->evaluate(self::at('2026-09-30 20:30', eatenKcal: 1114.0, targetKcal: 2350));

        self::assertStringEndsWith('il reste environ 1'.self::NBSP.'240'.self::NBSP.'kcal.', $drafts[0]->body, '1 236 → 1 240');
        self::assertStringEndsWith('il reste environ 1'.self::NBSP.'230'.self::NBSP.'kcal.', new LowCaloriesRule()->evaluate(self::at('2026-09-30 20:30', eatenKcal: 1116.0, targetKcal: 2350))[0]->body, '1 234 → 1 230');
    }

    public function testOneRecordOrSeveral(): void
    {
        $now = '2026-09-30 19:00';

        self::assertSame('Tu as battu un record pendant ta séance.', new RecordRule()->evaluate(self::at($now, records: [new RecordSession('w1', 1)]))[0]->body);
        self::assertSame('Tu as battu 2 records pendant ta séance.', new RecordRule()->evaluate(self::at($now, records: [new RecordSession('w1', 2)]))[0]->body);
    }

    public function testOnSundayTheLastSessionOfTheWeekIsToday(): void
    {
        // Sunday 4 October: one session left, one day left.
        self::assertCount(1, new WorkoutNotStartedRule()->evaluate(self::at('2026-10-04 18:30', planned: 3, done: 2)));
        self::assertSame([], new WorkoutNotStartedRule()->evaluate(self::at('2026-10-04 18:30', planned: 3, done: 3)), 'nothing left to do');
    }

    public function testQuietHoursStartOnTheirFirstMinute(): void
    {
        $hours = new QuietHours('13:00', '14:00');

        self::assertTrue($hours->covers(new \DateTimeImmutable('2026-09-30 13:00')));
        self::assertFalse($hours->covers(new \DateTimeImmutable('2026-09-30 14:00')));
    }

    /**
     * @param list<RecordSession> $records
     */
    private static function at(string $now, float $eatenKcal = 0.0, ?int $targetKcal = null, array $records = [], ?int $planned = null, int $done = 0): Situation
    {
        return new Situation(new \DateTimeImmutable($now, new \DateTimeZone('Europe/Paris')), $eatenKcal, $targetKcal, null, false, 0, [], $planned, $done, false, $records, null);
    }
}
