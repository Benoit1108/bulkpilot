<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * A session of the program ("Push", "Jambes"), its exercises in order.
 */
final readonly class TrainingDay
{
    public const int MAX_NAME_LENGTH = 40;
    public const int MAX_BLOCKS = 15;

    public string $name;
    /** @var list<ExerciseBlock> */
    public array $blocks;

    /**
     * @param list<ExerciseBlock> $blocks
     */
    public function __construct(string $name, array $blocks)
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidProgram::because(\sprintf('a day name has 1 to %d characters', self::MAX_NAME_LENGTH));
        }
        if ([] === $blocks || \count($blocks) > self::MAX_BLOCKS) {
            throw InvalidProgram::because(\sprintf('a day has 1 to %d blocks', self::MAX_BLOCKS));
        }
        $this->name = $name;
        $this->blocks = $blocks;
    }

    /**
     * @return list<ExerciseId>
     */
    public function exerciseIds(): array
    {
        $ids = [];
        foreach ($this->blocks as $block) {
            foreach ($block->exercises as $exercise) {
                $ids[] = $exercise->exerciseId;
            }
        }

        return $ids;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && \count($this->blocks) === \count($other->blocks)
            && array_all($this->blocks, static fn (ExerciseBlock $block, int $i): bool => $block->equals($other->blocks[$i]));
    }
}
