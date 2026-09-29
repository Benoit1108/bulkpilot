<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramVersion;

/**
 * A program with one of its versions spelled out (the current one unless asked otherwise).
 */
final readonly class ProgramView
{
    /**
     * @param list<array{name: string, blocks: list<list<PlannedExerciseView>>}> $days
     * @param list<array{number: int, createdAt: \DateTimeImmutable}>            $versions the latest first
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public int $currentVersion,
        public int $versionNumber,
        public \DateTimeImmutable $versionCreatedAt,
        public array $days,
        public array $versions,
    ) {
    }

    /**
     * @param array<string, Exercise>                                 $exercises by id, every one the version uses
     * @param list<array{number: int, createdAt: \DateTimeImmutable}> $history   the latest first
     */
    public static function of(Program $program, ProgramVersion $version, array $exercises, array $history): self
    {
        $days = [];
        foreach ($version->days() as $day) {
            $blocks = [];
            foreach ($day->blocks as $block) {
                $blocks[] = array_map(static fn ($planned): PlannedExerciseView => PlannedExerciseView::of($planned, $exercises[$planned->exerciseId->value]), $block->exercises);
            }
            $days[] = ['name' => $day->name, 'blocks' => $blocks];
        }

        return new self(
            $program->id()->value,
            $program->name(),
            $program->isActive(),
            $program->currentVersionNumber(),
            $version->number(),
            $version->createdAt(),
            $days,
            $history,
        );
    }
}
