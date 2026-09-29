<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application;

use App\Progression\Application\Measurement\AddMeasure;
use App\Progression\Application\Measurement\AddMeasureHandler;
use App\Progression\Application\Measurement\ArchiveMeasure;
use App\Progression\Application\Measurement\ArchiveMeasureHandler;
use App\Progression\Application\Measurement\GetMeasurements;
use App\Progression\Application\Measurement\GetMeasurementsHandler;
use App\Progression\Application\Measurement\GetMeasurementsSummary;
use App\Progression\Application\Measurement\GetMeasurementsSummaryHandler;
use App\Progression\Application\Measurement\ListMeasures;
use App\Progression\Application\Measurement\ListMeasuresHandler;
use App\Progression\Application\Measurement\MeasureSeries;
use App\Progression\Application\Measurement\RecordMeasurements;
use App\Progression\Application\Measurement\RecordMeasurementsHandler;
use App\Progression\Application\Measurement\RemoveMeasurements;
use App\Progression\Application\Measurement\RemoveMeasurementsHandler;
use App\Progression\Application\Measurement\RenameMeasure;
use App\Progression\Application\Measurement\RenameMeasureHandler;
use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Progression\Domain\Exception\MeasurementsNotFound;
use App\Progression\Domain\Exception\MeasureNotFound;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Progression\ProgressionTestCase;

final class MeasurementUseCasesTest extends ProgressionTestCase
{
    private function addMeasure(string $name, ?UserId $userId = null): string
    {
        $id = new CustomMeasureId($this->uuids->generate());
        new AddMeasureHandler($this->measures, $this->clock)(new AddMeasure($id, $userId ?? $this->alex, $name));

        return $id->value;
    }

    /**
     * @param array<string, float> $centimetres
     */
    private function measure(string $day, array $centimetres, ?UserId $userId = null): void
    {
        new RecordMeasurementsHandler($this->measurementDays, $this->measures, $this->clock)(new RecordMeasurements(new MeasurementDayId($this->uuids->generate()), $userId ?? $this->alex, self::day($day), $centimetres));
    }

    public function testTheBodyPartsComeFirstThenTheUsersOwnMeasures(): void
    {
        $hips = $this->addMeasure('  Hanches ');
        $this->addMeasure('Poignet', $this->sam);

        $measures = new ListMeasuresHandler($this->measures)(new ListMeasures($this->alex));

        self::assertCount(11, $measures);
        self::assertSame(['neck', null], [$measures[0]->key, $measures[0]->name]);
        self::assertSame([$hips, 'Hanches'], [$measures[10]->key, $measures[10]->name]);
    }

    public function testOwnMeasuresHaveDistinctNamesAndAreFewEnough(): void
    {
        $this->addMeasure('Hanches');
        try {
            $this->addMeasure('hanches');
            self::fail('two measures named alike');
        } catch (InvalidMeasurement $refused) {
            self::assertStringContainsString('already exists', $refused->getMessage());
        }
        $forearm = $this->addMeasure('Avant-bras');
        new RenameMeasureHandler($this->measures)(new RenameMeasure(new CustomMeasureId($forearm), $this->alex, 'Avant-bras gauche'));
        self::assertSame('Avant-bras gauche', $this->measures->measures[$forearm]->name());

        for ($i = 3; $i <= AddMeasureHandler::MAX_MEASURES; ++$i) {
            $this->addMeasure('Mesure '.$i);
        }
        $this->expectException(InvalidMeasurement::class);
        $this->addMeasure('Encore une');
    }

    public function testMeasuringAgainTheSameDayReplacesTheDay(): void
    {
        $hips = $this->addMeasure('Hanches');
        $this->measure('2026-09-29', ['left_arm' => 35.24, 'waist' => 80.0]);
        $this->measure('2026-09-29', ['waist' => 79.5, $hips => 95.0]);

        $day = new GetMeasurementsHandler($this->measurementDays)(new GetMeasurements($this->alex, self::day('2026-09-29')));
        self::assertSame(['waist' => 79.5, $hips => 95.0], $day->centimetres, 'the arm left out is not kept');
        self::assertCount(1, $this->measurementDays->days);
    }

    public function testOnlyTheUsersMeasuresStillTakenCanBeWritten(): void
    {
        $samsOwn = $this->addMeasure('Poignet', $this->sam);
        try {
            $this->measure('2026-09-29', [$samsOwn => 17.0]);
            self::fail('someone else\'s measure');
        } catch (MeasureNotFound) {
            self::assertSame([], $this->measurementDays->days);
        }

        $hips = $this->addMeasure('Hanches');
        new ArchiveMeasureHandler($this->measures, $this->clock)(new ArchiveMeasure(new CustomMeasureId($hips), $this->alex));
        $this->expectException(MeasureNotFound::class);
        $this->measure('2026-09-29', [$hips => 95.0]);
    }

    public function testMeasuringADayAgainKeepsTheValueOfAMeasureArchivedSince(): void
    {
        $wrist = $this->addMeasure('Poignet');
        $this->measure('2026-09-29', ['waist' => 80.0, $wrist => 17.0]);
        new ArchiveMeasureHandler($this->measures, $this->clock)(new ArchiveMeasure(new CustomMeasureId($wrist), $this->alex));

        $this->measure('2026-09-29', ['waist' => 79.5]);

        self::assertSame(['waist' => 79.5, $wrist => 17.0], new GetMeasurementsHandler($this->measurementDays)(new GetMeasurements($this->alex, self::day('2026-09-29')))->centimetres);
    }

    public function testNamesStayDistinctArchivedMeasuresIncluded(): void
    {
        $hips = $this->addMeasure('Hanches');
        $wrist = $this->addMeasure('Poignet');
        $rename = new RenameMeasureHandler($this->measures);
        $rename(new RenameMeasure(new CustomMeasureId($hips), $this->alex, 'HANCHES'));
        self::assertSame('HANCHES', $this->measures->measures[$hips]->name(), 'its own name in another case');

        new ArchiveMeasureHandler($this->measures, $this->clock)(new ArchiveMeasure(new CustomMeasureId($wrist), $this->alex));
        foreach ([fn () => $rename(new RenameMeasure(new CustomMeasureId($hips), $this->alex, 'poignet')), fn () => $this->addMeasure('Poignet')] as $attempt) {
            try {
                $attempt();
                self::fail('a name an archived measure has');
            } catch (InvalidMeasurement $refused) {
                self::assertStringContainsString('already exists', $refused->getMessage());
            }
        }
    }

    public function testAddingAndArchivingIsBoundedToo(): void
    {
        for ($i = 0; $i < AddMeasureHandler::MAX_EVER; ++$i) {
            $id = $this->addMeasure('Mesure '.$i);
            new ArchiveMeasureHandler($this->measures, $this->clock)(new ArchiveMeasure(new CustomMeasureId($id), $this->alex));
        }

        $this->expectException(InvalidMeasurement::class);
        $this->addMeasure('Une de plus');
    }

    public function testAMeasurementIsAPlausibleLengthOnADayThatHasCome(): void
    {
        foreach ([['2026-09-29', ['waist' => 0.4]], ['2026-10-01', ['waist' => 80.0]], ['2026-09-29', []]] as [$day, $centimetres]) {
            try {
                $this->measure($day, $centimetres);
                self::fail('accepted');
            } catch (InvalidMeasurement $refused) {
                self::assertSame('progression.invalid_measurement', $refused->errorCode());
            }
        }
    }

    public function testRemovingADayTwiceChangesNothing(): void
    {
        $this->measure('2026-09-29', ['waist' => 80.0]);
        $remove = new RemoveMeasurementsHandler($this->measurementDays);
        $remove(new RemoveMeasurements($this->alex, self::day('2026-09-29')));
        $remove(new RemoveMeasurements($this->alex, self::day('2026-09-29')));

        $this->expectException(MeasurementsNotFound::class);
        new GetMeasurementsHandler($this->measurementDays)(new GetMeasurements($this->alex, self::day('2026-09-29')));
    }

    public function testTheSummaryGivesEachMeasureOverThePeriod(): void
    {
        $hips = $this->addMeasure('Hanches');
        $old = $this->addMeasure('Poignet');
        $this->measure('2026-05-01', ['waist' => 76.0, 'left_arm' => 32.0, $old => 16.0]);
        $this->measure('2026-09-01', ['waist' => 78.0, $hips => 94.0]);
        $this->measure('2026-09-29', ['waist' => 79.2, $hips => 94.0]);
        new ArchiveMeasureHandler($this->measures, $this->clock)(new ArchiveMeasure(new CustomMeasureId($old), $this->alex));
        $this->measure('2026-09-29', ['waist' => 90.0], $this->sam);

        $series = new GetMeasurementsSummaryHandler($this->measurementDays, $this->measures)(new GetMeasurementsSummary($this->alex, Period::Month, self::day('2026-09-29')));
        $byKey = array_combine(array_map(static fn (MeasureSeries $one): string => $one->measure->key, $series), $series);

        self::assertCount(12, $series, 'the archived measure while it has values');
        self::assertSame([79.2, 1.2], [$byKey['waist']->latest?->cm, $byKey['waist']->changeCm]);
        self::assertCount(2, $byKey['waist']->points);
        self::assertSame([32.0, null, []], [$byKey['left_arm']->latest?->cm, $byKey['left_arm']->changeCm, $byKey['left_arm']->points], 'the latest ever, nothing in the period');
        self::assertSame(0.0, $byKey[$hips]->changeCm);
        self::assertNull($byKey['neck']->latest);
        self::assertTrue($byKey[$old]->measure->archived);
    }
}
