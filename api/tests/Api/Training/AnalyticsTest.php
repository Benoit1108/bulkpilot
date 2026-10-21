<?php

declare(strict_types=1);

namespace App\Tests\Api\Training;

use App\Tests\Support\Api\TrainingApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AnalyticsTest extends TrainingApiTestCase
{
    use ClockSensitiveTrait;

    public function testAPeriodWithoutWorkoutsIsEmptyWeeks(): void
    {
        $this->register();

        $analytics = $this->getJson('/api/training/analytics?period=30d');

        self::assertSame(['sessions' => 0, 'workingSets' => 0, 'reps' => 0, 'tonnageKg' => 0.0, 'records' => 0], self::array($analytics, 'totals'));
        self::assertGreaterThanOrEqual(5, \count(self::array($analytics, 'weeks')));
        self::assertSame(0.0, self::number($analytics, 'sessionsPerWeek'));
        $this->call('GET', '/api/training/analytics?period=forever');
        self::assertResponseStatusCodeSame(422);
    }

    public function testAFinishedWorkoutCountsItsWorkingSetsTonnageAndWeek(): void
    {
        // A Wednesday morning: the workout, "today" and its week never straddle midnight.
        self::mockTime('2026-09-30 10:00:00');
        $this->client->disableReboot();
        $this->register();
        $workout = '0199a0b1-0000-7000-8000-00000000d101';
        $at = static fn (string $when): string => new \DateTimeImmutable('2026-09-30 10:00:00')->modify($when)->format(\DateTimeInterface::ATOM);
        $this->call('PUT', '/api/workouts/'.$workout, ['startedAt' => $at('-1 hour')]);
        $this->call('PUT', '/api/workouts/'.$workout.'/exercises/0', ['exerciseId' => $this->exerciseId('developpe couche barre')]);
        foreach ([['501', 'warmup', 40, 10], ['502', 'normal', 80, 8], ['503', 'normal', 80, 7]] as [$set, $kind, $load, $reps]) {
            $this->call('PUT', '/api/workouts/'.$workout.'/sets/0199a0b1-0000-7000-8000-000000000'.$set, ['exerciseIndex' => 0, 'kind' => $kind, 'loadKg' => $load, 'reps' => $reps, 'doneAt' => $at('-30 minutes')]);
            self::assertResponseStatusCodeSame(204);
        }
        $this->call('POST', '/api/workouts/'.$workout.'/finish', ['finishedAt' => $at('now')]);

        $analytics = $this->getJson('/api/training/analytics?period=7d&today=2026-09-30');

        self::assertSame(['sessions' => 1, 'workingSets' => 2, 'reps' => 15, 'tonnageKg' => 1200.0, 'records' => 0], self::array($analytics, 'totals'), 'the warm-up left out; the first time is no record');
        $week = '2026-09-28';
        self::assertSame([1], array_values(array_map(static fn (mixed $one): int => self::int($one, 'sessions'), array_filter(self::array($analytics, 'weeks'), static fn (mixed $one): bool => $week === self::at($one, 'week')))));

        $week = $this->getJson('/api/training/week?today=2026-09-30');
        self::assertSame('2026-09-28', self::at($week, 'week'));
        self::assertSame([['muscle' => 'chest', 'workingSets' => 2]], self::array($week, 'muscles'), 'by the main muscle of the exercise');
        self::assertSame(['week' => '2026-09-21', 'sessions' => 0, 'workingSets' => 0, 'tonnageKg' => 0.0], self::array($week, 'lastWeek'));
        $this->call('GET', '/api/training/week?today=someday');
        self::assertResponseStatusCodeSame(400);

        $this->register('sam@example.com', 'Sam');
        self::assertSame(0, self::int($this->getJson('/api/training/analytics'), 'totals.sessions'));
        self::assertSame([], self::array($this->getJson('/api/training/week?today=2026-09-30'), 'muscles'));
    }
}
