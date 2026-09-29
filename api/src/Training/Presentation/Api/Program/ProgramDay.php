<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\ApiProperty;
use App\Training\Application\Program\PlannedExerciseView;

final readonly class ProgramDay
{
    /**
     * @param list<ProgramBlock> $blocks
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public array $blocks,
    ) {
    }

    /**
     * @param array{name: string, blocks: list<list<PlannedExerciseView>>} $day
     */
    public static function fromView(array $day): self
    {
        return new self($day['name'], array_map(static fn (array $block): ProgramBlock => new ProgramBlock(array_map(ProgramExercise::fromView(...), $block)), $day['blocks']));
    }
}
