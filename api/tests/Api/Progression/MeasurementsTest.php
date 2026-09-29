<?php

declare(strict_types=1);

namespace App\Tests\Api\Progression;

use App\Tests\Support\Api\ProgressionApiTestCase;

final class MeasurementsTest extends ProgressionApiTestCase
{
    public function testMeasuresAreTheBodyPartsThenTheUsersOwn(): void
    {
        $this->register();

        $hips = $this->call('POST', '/api/progression/measures', ['name' => 'Hanches'])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['name' => 'Hanches', 'custom' => true, 'archived' => false], array_intersect_key($hips, ['name' => 0, 'custom' => 0, 'archived' => 0]));

        $measures = $this->getJson('/api/progression/measures');
        self::assertCount(11, $measures);
        self::assertSame(['neck', null, false], [self::string($measures, '0.key'), self::at($measures, '0.name'), self::at($measures, '0.custom')]);

        $this->call('PUT', '/api/progression/measures/'.self::string($hips, 'key'), ['name' => 'Tour de hanches']);
        self::assertJsonContains(['name' => 'Tour de hanches']);
        $this->call('POST', '/api/progression/measures', ['name' => 'tour de HANCHES']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('progression.invalid_measurement');

        $this->call('DELETE', '/api/progression/measures/'.self::string($hips, 'key'));
        self::assertResponseStatusCodeSame(204);
        self::assertCount(10, $this->getJson('/api/progression/measures'));
    }

    public function testADayOfMeasurementsIsReplacedWhole(): void
    {
        $this->register();
        $hips = self::string($this->call('POST', '/api/progression/measures', ['name' => 'Hanches'])->toArray(), 'key');
        $day = $this->daysAgo(0);

        $this->call('PUT', '/api/progression/measurements/'.$day, ['centimetres' => ['left_arm' => 35.24, 'waist' => 80]]);
        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['day' => $day, 'centimetres' => ['left_arm' => 35.2, 'waist' => 80.0]]);
        $this->call('PUT', '/api/progression/measurements/'.$day, ['centimetres' => ['waist' => 79.5, $hips => 95]]);
        self::assertSame(['waist' => 79.5, $hips => 95.0], self::array($this->getJson('/api/progression/measurements/'.$day), 'centimetres'));

        $this->call('PUT', '/api/progression/measurements/'.$day, ['centimetres' => ['ankle' => 22]]);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('progression.measure_not_found');
        $this->call('PUT', '/api/progression/measurements/'.$day, ['centimetres' => ['waist' => 400]]);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/progression/measurements/'.$day, ['centimetres' => []]);
        self::assertResponseStatusCodeSame(422);
        $this->call('PUT', '/api/progression/measurements/2026-02-30', ['centimetres' => ['waist' => 80]]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('progression.invalid_day');

        $this->call('DELETE', '/api/progression/measurements/'.$day);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/progression/measurements/'.$day);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('progression.measurements_not_found');
    }

    public function testTheSummaryShowsEachMeasureOverThePeriodForItsOwnerOnly(): void
    {
        $this->register();
        $this->call('PUT', '/api/progression/measurements/'.$this->daysAgo(20), ['centimetres' => ['waist' => 78]]);
        $this->call('PUT', '/api/progression/measurements/'.$this->daysAgo(0), ['centimetres' => ['waist' => 79.2, 'chest' => 98]]);

        $summary = $this->getJson('/api/progression/measurements?period=30d&today='.$this->daysAgo(0));
        $waist = array_values(array_filter(self::array($summary, 'measures'), static fn (mixed $measure): bool => \is_array($measure) && 'waist' === ($measure['key'] ?? null)))[0] ?? null;
        self::assertSame(['day' => $this->daysAgo(0), 'cm' => 79.2], self::array($waist, 'latest'));
        self::assertSame(1.2, self::number($waist, 'changeCm'));
        self::assertCount(2, self::array($waist, 'points'));

        $this->register('sam@example.com', 'Sam');
        foreach (self::array($this->getJson('/api/progression/measurements'), 'measures') as $measure) {
            self::assertNull(self::at($measure, 'latest'));
        }
        $this->call('GET', '/api/progression/measurements/'.$this->daysAgo(0));
        self::assertResponseStatusCodeSame(404);
    }
}
