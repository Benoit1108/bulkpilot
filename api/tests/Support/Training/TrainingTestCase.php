<?php

declare(strict_types=1);

namespace App\Tests\Support\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Shared\SequentialUuidGenerator;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\RepRange;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Training use cases on in-memory adapters.
 */
abstract class TrainingTestCase extends TestCase
{
    protected const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    protected const string SAM = '00000000-0000-7000-8000-000000000511';

    protected InMemoryExerciseRepository $exercises;
    protected InMemoryExerciseNoteRepository $notes;
    protected InMemoryProgramRepository $programs;
    protected InMemoryProgramVersionRepository $versions;
    protected SequentialUuidGenerator $uuids;
    protected MockClock $clock;
    protected UserId $alex;
    protected UserId $sam;

    protected function setUp(): void
    {
        $this->exercises = new InMemoryExerciseRepository();
        $this->notes = new InMemoryExerciseNoteRepository();
        $this->programs = new InMemoryProgramRepository();
        $this->versions = new InMemoryProgramVersionRepository();
        $this->uuids = new SequentialUuidGenerator();
        $this->clock = new MockClock('2026-09-28 10:00:00');
        $this->alex = new UserId(self::ALEX);
        $this->sam = new UserId(self::SAM);
    }

    protected static function spec(string $name = 'Développé couché', Muscle $primary = Muscle::Chest, Measurement $measurement = Measurement::Weight): ExerciseSpec
    {
        return new ExerciseSpec($name, $primary, [Muscle::Triceps], ExerciseType::Compound, Equipment::Barbell, $measurement, new ExerciseDefaults(new RepRange(6, 10), 2, 180));
    }

    protected function libraryExercise(string $key, string $name, Muscle $primary = Muscle::Chest): Exercise
    {
        $exercise = Exercise::fromLibrary(new ExerciseId($this->uuids->generate()), $key, self::spec($name, $primary), $this->clock->now());
        $this->exercises->save($exercise);

        return $exercise;
    }
}
