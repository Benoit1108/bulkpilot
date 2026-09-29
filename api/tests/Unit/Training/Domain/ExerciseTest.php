<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidExercise;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteId;
use App\Training\Domain\Exercise\ExerciseSource;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Exercise\SearchKey;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ExerciseTest extends TestCase
{
    private const string EXERCISE = '00000000-0000-7000-8000-0000000000e1';

    private UserId $alex;
    private UserId $sam;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->sam = new UserId('00000000-0000-7000-8000-000000000511');
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testASpecIsTidied(): void
    {
        $spec = self::spec('  Rowing   barre ', [Muscle::Lats, Muscle::UpperBack, Muscle::Lats, Muscle::Biceps], mediaUrl: ' https://example.org/rowing.mp4 ', description: '  ');

        self::assertSame('Rowing barre', $spec->name);
        self::assertSame([Muscle::Lats, Muscle::Biceps], $spec->secondaryMuscles, 'the primary muscle and duplicates are dropped');
        self::assertSame('https://example.org/rowing.mp4', $spec->mediaUrl);
        self::assertNull($spec->description);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[TestWith([['name' => '']])]
    #[TestWith([['mediaUrl' => 'http://example.org/video']])]
    #[TestWith([['mediaUrl' => 'javascript:alert(1)']])]
    #[TestWith([['mediaUrl' => 'pas une adresse']])]
    #[TestWith([['secondary' => [Muscle::Chest, Muscle::Lats, Muscle::Traps, Muscle::Biceps, Muscle::Triceps, Muscle::Forearms, Muscle::Abs]]])]
    public function testAnInvalidSpecIsRefused(array $override): void
    {
        $this->expectException(InvalidExercise::class);

        /** @var list<Muscle> $secondary */
        $secondary = $override['secondary'] ?? [];
        self::spec(\is_string($override['name'] ?? null) ? $override['name'] : 'Tirage', $secondary, \is_string($override['mediaUrl'] ?? null) ? $override['mediaUrl'] : null);
    }

    public function testADescriptionIsBounded(): void
    {
        $this->expectException(InvalidExercise::class);

        self::spec(description: str_repeat('a', ExerciseSpec::MAX_DESCRIPTION_LENGTH + 1));
    }

    public function testALibraryExerciseIsSharedAndReadOnly(): void
    {
        $exercise = Exercise::fromLibrary(new ExerciseId(self::EXERCISE), 'barbell_row', self::spec(), $this->now);

        self::assertSame(ExerciseSource::Library, $exercise->source());
        self::assertSame('barbell_row', $exercise->libraryKey());
        self::assertTrue($exercise->isVisibleTo($this->alex));
        self::assertTrue($exercise->isVisibleTo($this->sam));
        self::assertFalse($exercise->isEditableBy($this->alex));

        $exercise->refreshFromLibrary(self::spec('Rowing barre pronation'));
        self::assertSame('Rowing barre pronation', $exercise->name());

        $this->expectException(InvalidExercise::class);
        $exercise->edit(self::spec('Mon rowing'));
    }

    public function testALibraryExerciseIsNeverDeleted(): void
    {
        $exercise = Exercise::fromLibrary(new ExerciseId(self::EXERCISE), 'barbell_row', self::spec(), $this->now);

        $this->expectException(InvalidExercise::class);
        $exercise->delete($this->now);
    }

    #[TestWith(['Barbell Row'])]
    #[TestWith([''])]
    #[TestWith(['a_key_that_is_far_too_long_to_be_a_library_key_of_sixty_chars_x'])]
    public function testALibraryKeyIsACode(string $key): void
    {
        $this->expectException(InvalidExercise::class);

        Exercise::fromLibrary(new ExerciseId(self::EXERCISE), $key, self::spec(), $this->now);
    }

    public function testAnOwnExerciseIsPrivateEditableAndSoftDeleted(): void
    {
        $exercise = Exercise::createCustom(new ExerciseId(self::EXERCISE), $this->alex, self::spec(), $this->now);
        self::assertSame(ExerciseSource::Custom, $exercise->source());
        self::assertNull($exercise->libraryKey());
        self::assertTrue($exercise->isEditableBy($this->alex));
        self::assertFalse($exercise->isVisibleTo($this->sam));
        self::assertFalse($exercise->isUsableBy($this->sam));

        $exercise->edit(self::spec('Rowing Yates', [Muscle::Biceps]));
        self::assertSame('Rowing Yates', $exercise->name());
        self::assertSame([Muscle::Biceps], $exercise->secondaryMuscles());
        self::assertSame($this->now, $exercise->createdAt());

        $exercise->delete($this->now);
        self::assertTrue($exercise->isDeleted());
        self::assertFalse($exercise->isVisibleTo($this->alex));
        self::assertTrue($exercise->isUsableBy($this->alex), 'still named in programs and history');
        self::assertFalse($exercise->isEditableBy($this->alex));
    }

    public function testDefaultsAreKept(): void
    {
        $spec = new ExerciseSpec('Planche', Muscle::Abs, [], ExerciseType::Isolation, Equipment::Bodyweight, Measurement::Duration, new ExerciseDefaults(new RepRange(30, 60), null, 60, new Tempo('3110')), 'Gainage', 'https://example.org/planche');
        $exercise = Exercise::createCustom(new ExerciseId(self::EXERCISE), $this->alex, $spec, $this->now);

        self::assertSame([30, 60, null, 60, '3-1-1-0'], [$exercise->defaults()->repRange->min, $exercise->defaults()->repRange->max, $exercise->defaults()->targetRir, $exercise->defaults()->restSeconds, $exercise->defaults()->tempo?->value]);
        self::assertSame([Muscle::Abs, ExerciseType::Isolation, Equipment::Bodyweight, Measurement::Duration], [$exercise->primaryMuscle(), $exercise->type(), $exercise->equipment(), $exercise->measurement()]);
        self::assertSame(['Gainage', 'https://example.org/planche'], [$exercise->description(), $exercise->mediaUrl()]);
        self::assertFalse(Measurement::Duration->countsReps());
        self::assertTrue(Measurement::Bodyweight->countsReps());
        self::assertSame($this->alex, $exercise->ownerId());
    }

    public function testANoteIsAShortCue(): void
    {
        $note = ExerciseNote::write(new ExerciseNoteId('00000000-0000-7000-8000-0000000000a0'), $this->alex, new ExerciseId(self::EXERCISE), '  Amplitude complète. ', $this->now);
        self::assertSame('Amplitude complète.', $note->text());

        $later = $this->now->modify('+1 day');
        $note->rewrite('Coudes serrés', $later);
        self::assertSame(['Coudes serrés', $later], [$note->text(), $note->updatedAt()]);

        $this->expectException(InvalidExercise::class);
        $note->rewrite(str_repeat('a', ExerciseNote::MAX_LENGTH + 1), $later);
    }

    public function testSearchIgnoresAccentsCaseAndPunctuation(): void
    {
        self::assertSame('developpe couche a la barre', SearchKey::of('Développé couché à la barre'));
        self::assertSame('rowing unilateral a l haltere', SearchKey::of('Rowing unilatéral à l’haltère'));
        self::assertSame(['oeuf', 'dos'], SearchKey::words('  ŒUF  dos dos '));
        self::assertSame([], SearchKey::words(' - '));
    }

    /**
     * @param list<Muscle> $secondary
     */
    private static function spec(string $name = 'Rowing barre', array $secondary = [Muscle::Biceps], ?string $mediaUrl = null, ?string $description = null): ExerciseSpec
    {
        return new ExerciseSpec($name, Muscle::UpperBack, $secondary, ExerciseType::Compound, Equipment::Barbell, Measurement::Weight, new ExerciseDefaults(new RepRange(6, 10), 2, 150), $description, $mediaUrl);
    }
}
