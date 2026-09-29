<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidExercise;

/**
 * A user's standing cue for an exercise ("amplitude complète"), shown every time they do it
 * (spec §41), whether the exercise is theirs or from the library.
 */
class ExerciseNote extends AggregateRoot
{
    public const int MAX_LENGTH = 500;

    private string $text;

    private function __construct(
        private readonly ExerciseNoteId $id,
        private readonly UserId $ownerId,
        private readonly ExerciseId $exerciseId,
        string $text,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->text = self::validText($text);
    }

    public static function write(ExerciseNoteId $id, UserId $owner, ExerciseId $exerciseId, string $text, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, $exerciseId, $text, $now);
    }

    public function rewrite(string $text, \DateTimeImmutable $now): void
    {
        $this->text = self::validText($text);
        $this->updatedAt = $now;
    }

    public function id(): ExerciseNoteId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function exerciseId(): ExerciseId
    {
        return $this->exerciseId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function validText(string $text): string
    {
        $text = trim($text);
        if ('' === $text || mb_strlen($text) > self::MAX_LENGTH) {
            throw InvalidExercise::because(\sprintf('a note has 1 to %d characters', self::MAX_LENGTH));
        }

        return $text;
    }
}
