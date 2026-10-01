<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Application;

use App\Coach\Application\Analysis\AnalyseAccount;
use App\Coach\Application\Analysis\AnalyseAccountHandler;
use App\Coach\Application\Analysis\RunNightlyAnalyses;
use App\Coach\Application\Analysis\RunNightlyAnalysesHandler;
use App\Tests\Support\Coach\CoachTestCase;
use App\Tests\Support\Shared\FakeAccounts;
use App\Tests\Support\Shared\SpyCommandBus;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

/**
 * The nightly analyses (ADR-0029): each account at 3 in the morning of its own time zone.
 */
final class NightlyAnalysesTest extends CoachTestCase
{
    /**
     * @return list<array{string, string}>
     */
    private static function sent(SpyCommandBus $bus): array
    {
        return array_map(static fn (object $analysis): array => $analysis instanceof AnalyseAccount ? [$analysis->userId->value, $analysis->today->format('Y-m-d')] : ['', ''], $bus->dispatched);
    }

    public function testEachAccountIsAnalysedInTheFirstQuarterOfThreeOrFourInItsOwnNight(): void
    {
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Europe/Paris');
        $accounts->add(self::SAM, 'America/New_York');

        self::assertSame([[self::ALEX, '2026-09-30']], $this->nightly($accounts, '2026-09-30 01:14:00 UTC'), '03:14 in Paris, 21:14 the day before in New York');
        self::assertSame([[self::ALEX, '2026-09-30']], $this->nightly($accounts, '2026-09-30 02:05:00 UTC'), '04:05 in Paris: again, which changes nothing');
        self::assertSame([], $this->nightly($accounts, '2026-09-30 00:59:00 UTC'), '02:59 in Paris');
        self::assertSame([], $this->nightly($accounts, '2026-09-30 01:15:00 UTC'), '03:15 in Paris: that quarter was the last one');
        self::assertSame([], $this->nightly($accounts, '2026-09-30 03:00:00 UTC'), '05:00 in Paris');
        self::assertSame([[self::SAM, '2026-09-30']], $this->nightly($accounts, '2026-09-30 07:10:00 UTC'), '03:10 in New York');
    }

    public function testTheDayIsTheAccountsOwnEvenWhenItIsTomorrowInUtc(): void
    {
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Asia/Tokyo');

        self::assertSame([[self::ALEX, '2026-10-01']], $this->nightly($accounts, '2026-09-30 18:05:00 UTC'), '03:05 on the 1st in Tokyo');
    }

    public function testASpringForwardNightThatSkipsThreeStillHasItsAnalysis(): void
    {
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Europe/Helsinki');

        // 2027-03-28: Helsinki goes from 02:59 (00:59 UTC) straight to 04:00 (01:00 UTC).
        self::assertSame([], $this->nightly($accounts, '2027-03-28 00:45:00 UTC'), '02:45');
        self::assertSame([[self::ALEX, '2027-03-28']], $this->nightly($accounts, '2027-03-28 01:00:00 UTC'), '04:00, 3:00 never came');
    }

    public function testAnAccountThatFailsDoesNotStopTheOthers(): void
    {
        $accounts = new FakeAccounts();
        $accounts->add(self::ALEX, 'Not/AZone');
        $accounts->add(self::SAM, 'Europe/Paris');

        self::assertSame([[self::SAM, '2026-09-30']], $this->nightly($accounts, '2026-09-30 01:05:00 UTC'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function nightly(FakeAccounts $accounts, string $utc): array
    {
        $bus = new SpyCommandBus();
        new RunNightlyAnalysesHandler($accounts, $bus, new MockClock(new \DateTimeImmutable($utc)), new NullLogger())(new RunNightlyAnalyses());

        return self::sent($bus);
    }

    public function testTheNightlyAnalysisRunsOnceThatDay(): void
    {
        $accounts = new FakeAccounts();
        $accounts->add($this->alex->value, 'Europe/Paris');
        $nightly = new AnalyseAccountHandler($this->handler(), $accounts);

        $nightly(new AnalyseAccount($this->alex, new \DateTimeImmutable(self::TODAY)));
        $offered = \count($this->recommendations->openOf($this->alex));
        $nightly(new AnalyseAccount($this->alex, new \DateTimeImmutable(self::TODAY)));

        self::assertGreaterThan(0, $offered);
        self::assertCount($offered, $this->recommendations->openOf($this->alex));
        self::assertTrue($this->settings->ofOrDefault($this->alex, $this->clock->now())->hasAnalysed(new \DateTimeImmutable(self::TODAY)));
    }

    public function testAQueuedAnalysisOfAnAccountClosedSinceDoesNothing(): void
    {
        new AnalyseAccountHandler($this->handler(), new FakeAccounts())(new AnalyseAccount($this->alex, new \DateTimeImmutable(self::TODAY)));

        self::assertSame([], $this->recommendations->openOf($this->alex), 'its deletion is pending, or it is gone: nothing more is made for it');
    }
}
