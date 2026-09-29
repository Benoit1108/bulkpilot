<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Application\Weight\RecordWeighIn;
use App\Progression\Application\Weight\RecordWeighInHandler;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighInId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Tests\Support\Shared\SpyEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Progression use cases on in-memory adapters; the clock is on 2026-09-29.
 */
abstract class ProgressionTestCase extends TestCase
{
    protected const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    protected const string SAM = '00000000-0000-7000-8000-000000000511';

    protected InMemoryWeighInRepository $weighIns;
    protected InMemoryWeightGoalRepository $goals;
    protected InMemoryCustomMeasureRepository $measures;
    protected InMemoryMeasurementDayRepository $measurementDays;
    protected SpyEventBus $events;
    protected SequentialUuidGenerator $uuids;
    protected MockClock $clock;
    protected UserId $alex;
    protected UserId $sam;

    protected function setUp(): void
    {
        $this->weighIns = new InMemoryWeighInRepository();
        $this->goals = new InMemoryWeightGoalRepository();
        $this->measures = new InMemoryCustomMeasureRepository();
        $this->measurementDays = new InMemoryMeasurementDayRepository();
        $this->events = new SpyEventBus();
        $this->uuids = new SequentialUuidGenerator();
        $this->clock = new MockClock('2026-09-29 08:00:00');
        $this->alex = new UserId(self::ALEX);
        $this->sam = new UserId(self::SAM);
    }

    protected static function day(string $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day);
    }

    /**
     * @param array<string, float> $weights by day
     */
    protected function weigh(array $weights, ?UserId $userId = null): void
    {
        $handler = new RecordWeighInHandler($this->weighIns, $this->events, $this->clock);
        foreach ($weights as $day => $kg) {
            $handler(new RecordWeighIn(new WeighInId($this->uuids->generate()), $userId ?? $this->alex, self::day($day), $kg));
        }
    }

    /**
     * @param array<string, float> $weights by day
     *
     * @return list<DayWeight> the oldest first
     */
    protected static function series(array $weights): array
    {
        ksort($weights);

        return array_map(static fn (string $day, float $kg): DayWeight => new DayWeight(self::day($day), $kg), array_keys($weights), array_values($weights));
    }
}
