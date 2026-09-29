<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidExercise;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;

/**
 * A movement: from the shared library (read-only, refreshed by its import) or a user's own.
 * Programs and workouts refer to it; deleting one's own exercise is a soft delete, so that
 * programs and history keep their names.
 */
class Exercise extends AggregateRoot
{
    private int $version = 1;
    private ?\DateTimeImmutable $deletedAt = null;
    private string $name;
    private Muscle $primaryMuscle;
    /** @var list<Muscle> */
    private array $secondaryMuscles;
    private ExerciseType $type;
    private Equipment $equipment;
    private Measurement $measurement;
    private ?string $description;
    private ?string $mediaUrl;
    private RepRange $repRange;
    private ?int $targetRir;
    private int $restSeconds;
    private ?Tempo $tempo;
    private string $searchKey;
    /** Every muscle it works, space-separated and padded (" chest triceps "): what a muscle filter matches. */
    private string $muscleKey;

    private function __construct(
        private readonly ExerciseId $id,
        private readonly ExerciseSource $source,
        private readonly ?string $libraryKey,
        private readonly ?UserId $ownerId,
        ExerciseSpec $spec,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->describe($spec);
    }

    public static function fromLibrary(ExerciseId $id, string $key, ExerciseSpec $spec, \DateTimeImmutable $now): self
    {
        if (1 !== preg_match('/^[a-z0-9_]{1,60}$/', $key)) {
            throw InvalidExercise::because('a library key has 1 to 60 lowercase letters, digits or underscores');
        }

        return new self($id, ExerciseSource::Library, $key, null, $spec, $now);
    }

    public static function createCustom(ExerciseId $id, UserId $owner, ExerciseSpec $spec, \DateTimeImmutable $now): self
    {
        return new self($id, ExerciseSource::Custom, null, $owner, $spec, $now);
    }

    public function refreshFromLibrary(ExerciseSpec $spec): void
    {
        if (ExerciseSource::Library !== $this->source) {
            throw InvalidExercise::because('only library exercises are refreshed from the library');
        }
        $this->describe($spec);
    }

    public function edit(ExerciseSpec $spec): void
    {
        $this->assertCustom();
        $this->describe($spec);
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->assertCustom();
        $this->deletedAt ??= $now;
    }

    public function isVisibleTo(UserId $userId): bool
    {
        return null === $this->deletedAt && $this->isUsableBy($userId);
    }

    /**
     * Visible, or one of the user's own even deleted: programs and history still show it.
     */
    public function isUsableBy(UserId $userId): bool
    {
        return null === $this->ownerId || $this->ownerId->equals($userId);
    }

    public function isEditableBy(UserId $userId): bool
    {
        return null === $this->deletedAt && null !== $this->ownerId && $this->ownerId->equals($userId);
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function id(): ExerciseId
    {
        return $this->id;
    }

    public function source(): ExerciseSource
    {
        return $this->source;
    }

    public function libraryKey(): ?string
    {
        return $this->libraryKey;
    }

    public function ownerId(): ?UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function primaryMuscle(): Muscle
    {
        return $this->primaryMuscle;
    }

    /**
     * @return list<Muscle>
     */
    public function secondaryMuscles(): array
    {
        return $this->secondaryMuscles;
    }

    public function type(): ExerciseType
    {
        return $this->type;
    }

    public function equipment(): Equipment
    {
        return $this->equipment;
    }

    public function measurement(): Measurement
    {
        return $this->measurement;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function mediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function defaults(): ExerciseDefaults
    {
        return new ExerciseDefaults($this->repRange, $this->targetRir, $this->restSeconds, $this->tempo);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function describe(ExerciseSpec $spec): void
    {
        $this->name = $spec->name;
        $this->primaryMuscle = $spec->primaryMuscle;
        $this->secondaryMuscles = $spec->secondaryMuscles;
        $this->type = $spec->type;
        $this->equipment = $spec->equipment;
        $this->measurement = $spec->measurement;
        $this->description = $spec->description;
        $this->mediaUrl = $spec->mediaUrl;
        $this->repRange = $spec->defaults->repRange;
        $this->targetRir = $spec->defaults->targetRir;
        $this->restSeconds = $spec->defaults->restSeconds;
        $this->tempo = $spec->defaults->tempo;
        $this->searchKey = SearchKey::of($spec->name);
        $this->muscleKey = ' '.implode(' ', array_map(static fn (Muscle $muscle): string => $muscle->value, [$spec->primaryMuscle, ...$spec->secondaryMuscles])).' ';
    }

    private function assertCustom(): void
    {
        if (null === $this->ownerId) {
            throw InvalidExercise::because('library exercises cannot be changed');
        }
    }
}
