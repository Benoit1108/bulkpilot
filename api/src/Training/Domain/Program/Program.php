<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidProgram;

/**
 * A training plan ("PPL Masse", "Full Body", "Vacances"). Several can coexist, one at most is
 * active (spec §48). Its structure lives in versions: renaming it is not a new version,
 * changing its days is (spec §49).
 */
class Program extends AggregateRoot
{
    public const int MAX_NAME_LENGTH = 100;
    /** Far more than a lifetime of edits; keeps a runaway client from filling the table. */
    public const int MAX_VERSIONS = 500;

    private int $version = 1;
    private bool $active = false;
    private ?\DateTimeImmutable $deletedAt = null;
    private string $name;

    private function __construct(
        private readonly ProgramId $id,
        private readonly UserId $ownerId,
        string $name,
        private ProgramVersionId $currentVersionId,
        private int $currentVersionNumber,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->name = self::validName($name);
    }

    public static function create(ProgramId $id, UserId $owner, string $name, ProgramVersion $first, \DateTimeImmutable $now): self
    {
        if (!$first->programId()->equals($id) || 1 !== $first->number()) {
            throw InvalidProgram::because('a program starts with its first version');
        }

        return new self($id, $owner, $name, $first->id(), 1, $now);
    }

    public function rename(string $name): void
    {
        $this->name = self::validName($name);
    }

    /**
     * The next version when the days changed, null when they did not (nothing to record).
     *
     * @param list<TrainingDay> $days
     */
    public function revise(ProgramVersion $current, ProgramVersionId $nextId, array $days, \DateTimeImmutable $now): ?ProgramVersion
    {
        if (!$current->id()->equals($this->currentVersionId)) {
            throw InvalidProgram::because('a program is revised from its current version');
        }
        if ($current->hasDays($days)) {
            return null;
        }
        if ($this->currentVersionNumber >= self::MAX_VERSIONS) {
            throw InvalidProgram::because(\sprintf('a program has at most %d versions: make a new one', self::MAX_VERSIONS));
        }
        $next = $current->next($nextId, $days, $now);
        $this->currentVersionId = $next->id();
        $this->currentVersionNumber = $next->number();

        return $next;
    }

    public function activate(): void
    {
        if (null !== $this->deletedAt) {
            throw InvalidProgram::because('an archived program cannot be activated');
        }
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * Archived: gone from the list, while past workouts keep their version.
     */
    public function archive(\DateTimeImmutable $now): void
    {
        $this->active = false;
        $this->deletedAt ??= $now;
    }

    public function isVisibleTo(UserId $userId): bool
    {
        return null === $this->deletedAt && $this->ownerId->equals($userId);
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function id(): ProgramId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function currentVersionId(): ProgramVersionId
    {
        return $this->currentVersionId;
    }

    public function currentVersionNumber(): int
    {
        return $this->currentVersionNumber;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidProgram::because(\sprintf('a program name has 1 to %d characters', self::MAX_NAME_LENGTH));
        }

        return $name;
    }
}
